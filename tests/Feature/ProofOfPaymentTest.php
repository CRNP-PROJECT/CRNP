<?php

declare(strict_types=1);

use App\Orders\OrderStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * #24 — proof of payment: attach, auto-verify, flagged hold.
 *
 * From the tracker, one Attach button opens the native picker on the newest
 * image and auto-uploads with no Submit. The selection is compressed
 * client-side first (adaptive JPEG, ≤300 KB, ≥720 px floor) and re-encoded to
 * the same ceiling server-side — a 3 MB screenshot never reaches RTDB. Two
 * fields follow: amount paid (prefilled = order total) and GCash ref#. The
 * proof auto-verifies on arrival when amount = order total and ref# is
 * present; anything else lands a flagged hold. Proofs live in the sibling
 * `proofs/{id}` node, so list/queue/tracker/board reads stay scalar.
 */

beforeEach(function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea', 'price' => 200.0]);
    seedSettings([
        'hours' => weekdayHours(),
        'gcash' => ['number' => '0917 123 4567', 'qr' => 'data:image/jpeg;base64,QRDATA'],
    ]);

    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

/**
 * A deterministic, high-entropy JPEG so the encoder has to work and the server
 * re-encode is exercised — a flat colour would fit under the ceiling untouched.
 */
function proofNoisyJpeg(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    mt_srand(24);

    for ($x = 0; $x < $width; $x += 2) {
        for ($y = 0; $y < $height; $y += 2) {
            $colour = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, $colour);
        }
    }

    ob_start();
    imagejpeg($image, null, 95);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function proofUpload(int $width = 2000, int $height = 1500): UploadedFile
{
    $path = testTempFile('crnp-proof-', '.jpg');

    file_put_contents($path, proofNoisyJpeg($width, $height));

    return new UploadedFile($path, 'gcash-proof.jpg', 'image/jpeg', null, true);
}

/**
 * Arrange an online order exactly as #23's place-order action writes it.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedProofOrder(string $uid, float $total, array $overrides = []): string
{
    $id = (string) Str::ulid();

    rtdb()->seed("orders/{$id}", [
        'code' => 'PRF1',
        'type' => 'online',
        'status' => OrderStatus::AwaitingPayment->value,
        'lines' => [],
        'total' => $total,
        'payments' => [],
        'change' => 0.0,
        'customerUid' => $uid,
        'placedAt' => '2026-10-06T04:00:00Z',
        'pickupAt' => null,
        'deadlineAt' => '2026-10-06T04:15:00Z',
        ...$overrides,
    ]);

    rtdb()->update("users/{$uid}", ['activeOrderId' => $id]);

    return $id;
}

function submitProof(string $orderId, UploadedFile $image, string $amount, ?string $ref): \Illuminate\Testing\TestResponse
{
    return test()->post(route('orders.proof.store', ['order' => $orderId]), [
        'image' => $image,
        'amount' => $amount,
        'ref' => $ref,
    ]);
}

it('shows the Attach button, the newest-image picker, and the amount prefilled with the total', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    $this->get(route('orders.show', ['order' => $id]))
        ->assertOk()
        ->assertSee('Attach payment screenshot')
        ->assertSee('accept="image/*"', false)
        // The amount field is prefilled with the order total — edit only what differs.
        ->assertSee('value="400.00"', false)
        ->assertSee('name="ref"', false)
        // The proof uploads on selection — there is no Submit control.
        ->assertSee('data-proof-upload', false)
        ->assertDontSee('name="submit-proof"', false);
});

it('stores a re-encoded proof at proofs/{id} under the 300KB ceiling and 720px floor', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    submitProof($id, proofUpload(2000, 1500), '400.00', 'GC1234567890')->assertNoContent();

    $proof = rtdb()->get("proofs/{$id}");

    expect($proof)->toBeArray()
        ->and($proof['imageBase64'])->toBeString();

    $bytes = base64_decode((string) $proof['imageBase64'], true);

    expect($bytes)->toBeString();

    $info = getimagesizefromstring((string) $bytes);

    expect($info)->not->toBeFalse()
        ->and($info[2])->toBe(IMAGETYPE_JPEG)
        ->and(strlen((string) $bytes))->toBeLessThanOrEqual(300 * 1024)
        ->and(max($info[0], $info[1]))->toBeGreaterThanOrEqual(720);
});

it('never stores the proof image bytes on the order node', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    submitProof($id, proofUpload(2000, 1500), '400.00', 'GC1234567890')->assertNoContent();

    $order = json_encode(rtdb()->get("orders/{$id}"));

    expect($order)->toBeString()
        ->and($order)->not->toContain('imageBase64')
        ->and($order)->not->toContain('data:image');
});

it('keeps the tracker state and the kitchen read free of image bytes', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    submitProof($id, proofUpload(2000, 1500), '400.00', 'GC1234567890')->assertNoContent();

    $state = json_encode($this->get(route('orders.state', ['order' => $id]))->json());

    expect($state)->toBeString()
        ->and($state)->not->toContain('imageBase64')
        ->and($state)->not->toContain('data:image');

    // Verified orders reach the kitchen board (#28); the board's read of the
    // order node never carries the sibling proof image either.
    expect(onKitchenBoard())->toContain($id);

    $boardOrder = json_encode(rtdb()->get("orders/{$id}"));

    expect($boardOrder)->toBeString()
        ->and($boardOrder)->not->toContain('imageBase64')
        ->and($boardOrder)->not->toContain('data:image');
});

it('auto-verifies when the amount matches the total and the ref# is present', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    $this->post(route('orders.proof.start', ['order' => $id]))->assertNoContent();

    submitProof($id, proofUpload(), '400.00', 'GC1234567890')->assertNoContent();

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Cooking->value)
        ->and($order['verifiedAt'])->toBeString()
        ->and($order['verification'])->toBeArray()
        ->and($order['verification']['amount'])->toBe(400.0)
        ->and($order['verification']['ref'])->toBe('GC1234567890')
        ->and($order['verification']['verifiedAt'])->toBeString();

    // The kitchen ticket appears the same instant: the order is now cookable.
    expect(onKitchenBoard())->toContain($id);
});

it('lands a flagged hold when the entered amount does not match the total', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    submitProof($id, proofUpload(), '350.00', 'GC1234567890')->assertNoContent();

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Verifying->value)
        ->and($order['enteredAmount'])->toBe(350.0)
        ->and($order['gcashRef'])->toBe('GC1234567890')
        ->and($order)->not->toHaveKey('verification');

    // The kitchen waits on the cashier's one-tap Approve / Reject: a flagged
    // hold is approvable (Approve promotes it to cooking and the ticket appears),
    // but until the tap there is no ticket on the board.
    expect(onKitchenBoard())->not->toContain($id);
});

it('lands a flagged hold when the ref# is blank', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    submitProof($id, proofUpload(), '400.00', '   ')->assertNoContent();

    $order = rtdb()->get("orders/{$id}");

    expect($order['status'])->toBe(OrderStatus::Verifying->value)
        ->and($order['enteredAmount'])->toBe(400.0)
        ->and($order['gcashRef'])->toBeNull();
});

it('shows "Payment needs checking" with the entered amount and order total on a flagged hold', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    submitProof($id, proofUpload(), '350.00', 'GC1234567890')->assertNoContent();

    $this->get(route('orders.state', ['order' => $id]))
        ->assertOk()
        ->assertJson([
            'status' => 'verifying',
            'label' => 'Payment needs checking — see the counter',
            'enteredAmount' => 350.0,
            'total' => 400.0,
        ]);

    $this->get(route('orders.show', ['order' => $id]))
        ->assertOk()
        ->assertSee('Payment needs checking — see the counter')
        ->assertSee('350.00')
        ->assertSee('400.00');
});

it('persists an in-flight marker when the upload begins and clears it on arrival', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    $this->post(route('orders.proof.start', ['order' => $id]))->assertNoContent();

    $order = rtdb()->get("orders/{$id}");

    expect($order['proofInFlight'])->toBeTrue()
        ->and($order['uploadStartedAt'])->toBeString();

    submitProof($id, proofUpload(), '400.00', 'GC1234567890')->assertNoContent();

    $order = rtdb()->get("orders/{$id}");

    expect($order['proofInFlight'])->toBeFalse()
        ->and($order['proofReceivedAt'])->toBeString();
});

it('does not let one customer attach a proof to another account\'s order', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    auth()->logout();
    signIn('grace@example.com', 'Grace Hopper', 'sub-grace');

    $this->post(route('orders.proof.start', ['order' => $id]))->assertForbidden();
    submitProof($id, proofUpload(), '400.00', 'GC1234567890')->assertForbidden();

    expect(rtdb()->get("proofs/{$id}"))->toBeNull();
});

it('refuses a proof that is not an image', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    $this->post(route('orders.proof.store', ['order' => $id]), [
        'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        'amount' => '400.00',
        'ref' => 'GC1234567890',
    ])->assertSessionHasErrors('image');

    expect(rtdb()->get("proofs/{$id}"))->toBeNull();
});

it('refuses a proof once the order has left the payment states', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0, ['status' => OrderStatus::Served->value]);

    submitProof($id, proofUpload(), '400.00', 'GC1234567890')->assertForbidden();

    expect(rtdb()->get("proofs/{$id}"))->toBeNull();
});

it('evicts the kitchen board the same instant a proof auto-verifies', function (): void {
    seedSettings([
        'hours' => weekdayHours(),
        'gcash' => ['number' => '0917 123 4567', 'qr' => 'data:image/jpeg;base64,QRDATA'],
        'kitchenSecret' => 'k-secret-123',
    ]);

    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    // Warm the coalesced board entry: the awaiting-payment order is not on it.
    $this->get('/kitchen/k-secret-123')->assertOk()->assertDontSee('PRF1');

    submitProof($id, proofUpload(), '400.00', 'GC1234567890')->assertNoContent();

    // No time travel: SubmitProof evicts the board entry, so the new ticket
    // appears on the very next read rather than after the 5 s TTL.
    $this->get('/kitchen/k-secret-123')->assertOk()->assertSee('PRF1');
});

it('gives the order tracker poll a pause control (WCAG 2.2 SC 2.2.2)', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    $html = $this->get(route('orders.show', ['order' => $id]))->assertOk()->getContent();

    expect($html)->toBeString()
        ->and($html)->toContain('id="tracker-pause"')
        ->and($html)->toContain('Pause updates')
        ->and($html)->toContain('aria-pressed="false"')
        ->and($html)->toContain('setPaused(!paused)');
});

it('wires the client to auto-upload on selection with the proof ceiling and one retry', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    $html = $this->get(route('orders.show', ['order' => $id]))->assertOk()->getContent();

    // Auto-upload: the change handler fires on selection, no Submit control.
    expect($html)->toBeString()
        ->and($html)->toContain("input.addEventListener('change', begin)")
        ->and($html)->not->toContain('name="submit-proof"')
        // Client compression bounds: adaptive JPEG ≤300 KB, ≥720 px floor.
        ->and($html)->toContain('var MAX_BYTES = 300 * 1024')
        ->and($html)->toContain('var FLOOR_EDGE = 720')
        ->and($html)->toContain("canvas.toDataURL('image/jpeg'")
        // One automatic retry, then the manual "tap to retry".
        ->and($html)->toContain('if (attempt < 1)')
        ->and($html)->toContain('Upload failed — tap to retry')
        // In-flight marker persisted when the upload begins.
        ->and($html)->toContain('markInFlight()')
        // Motion toned down, not removed, under prefers-reduced-motion.
        ->and($html)->toContain('prefers-reduced-motion: reduce');
});

it('includes the proof client inline without a nested script tag that would break the tracker', function (): void {
    // The proof client is a partial included INSIDE the tracker's own <script>
    // block, so it must carry no <script>/</script> wrapper of its own — a
    // nested tag closes the outer block early and the Attach button stops
    // responding.
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $id = seedProofOrder('sub-ada', 400.0);

    $html = $this->get(route('orders.show', ['order' => $id]))->assertOk()->getContent();

    expect($html)->toBeString()
        // No `</script>` immediately followed (across whitespace) by another
        // `</script>` — the tell-tale of a double-wrapped partial.
        ->and(preg_match('/<\/script>\s*<\/script>/', $html))->toBe(0)
        // The Attach button and its listener land exactly once each.
        ->and(substr_count($html, 'type="button" data-proof-attach'))->toBe(1)
        ->and(substr_count($html, "input.addEventListener('change', begin)"))->toBe(1);
});
