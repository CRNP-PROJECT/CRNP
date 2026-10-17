<?php

declare(strict_types=1);

use App\Cache\AvailabilityCache;
use App\Rentals\RentalCatalog;
use App\Rentals\RentalStatus;
use App\Rtdb\Contracts\RtdbClient;
use App\Staff\StaffRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Support\RecordingRtdbClient;

/**
 * #34 — Rentals: catalog, booking, handover/return.
 *
 * The public rental catalog shows price/day and per-date availability computed
 * as stock vs date-range overlaps of confirmed bookings, behind the same
 * open/closed badge as place-order. Booking is sign-in-gated, takes a date range
 * + quantity, computes the total, and sends the GCash QR through the same
 * payment gate as orders (proof attach, amount/ref flag, 15-minute window). An
 * unpaid booking lapses with stock untouched; prepaid in full, no deposit. At
 * the counter, Hand over lowers stock and Confirm return raises it, with a quiet
 * overdue row and no penalty. Admin manages rental items + Add stock with
 * steppers. Rentals never enter the order stream.
 */

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-06 10:00:00', 'Asia/Manila'));

    seedSettings([
        'hours' => weekdayHours(),
        'gcash' => ['number' => '0917 123 4567', 'qr' => 'data:image/jpeg;base64,QRDATA'],
    ]);
});

/**
 * The availability figure rendered for one date cell in the catalog markup.
 */
function availabilityOn(string $html, string $date): string
{
    if (preg_match('/data-date="' . preg_quote($date, '/') . '".*?rental-day__count[^>]*>\s*([0-9]+ \/ [0-9]+)/s', $html, $matches) !== 1) {
        return '';
    }

    return $matches[1];
}

/**
 * Place a rental booking through the item-page POST.
 */
function bookRental(string $item, string $from, string $to, int $qty = 1): \Illuminate\Testing\TestResponse
{
    return test()->post(route('rentals.book', ['item' => $item]), [
        'dateFrom' => $from,
        'dateTo' => $to,
        'qty' => $qty,
    ]);
}

/**
 * A deterministic, high-entropy JPEG for the server re-encode path.
 */
function rentalNoisyJpeg(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    mt_srand(34);

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

function rentalProofUpload(int $width = 2000, int $height = 1500): UploadedFile
{
    $path = testTempFile('crnp-rental-proof-', '.jpg');

    file_put_contents($path, rentalNoisyJpeg($width, $height));

    return new UploadedFile($path, 'gcash-proof.jpg', 'image/jpeg', null, true);
}

// --- Catalog --------------------------------------------------------------

it('shows each rental item with its image and price per day', function (): void {
    seedRentalItem('r1', ['name' => 'Folding chair', 'pricePerDay' => 50.0, 'units' => 3]);

    $this->get('/rentals')
        ->assertOk()
        ->assertSee('Folding chair')
        ->assertSee('₱50.00')
        ->assertSee('per day');
});

it('computes per-date availability as stock minus overlapping confirmed bookings', function (): void {
    seedRentalItem('r1', ['name' => 'Folding chair', 'units' => 3]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'dateFrom' => '2026-10-08',
        'dateTo' => '2026-10-09',
        'qty' => 2,
        'status' => 'confirmed',
    ]);

    $html = $this->get('/rentals')->assertOk()->getContent();

    // 2026-10-08 and 10-09: 3 - 2 = 1 available; 2026-10-10: 3 - 0 = 3.
    expect($html)->toBeString()
        ->and($html)->toContain('data-date="2026-10-08"')
        ->and($html)->toContain('data-date="2026-10-09"')
        ->and($html)->toContain('data-date="2026-10-10"');

    expect(availabilityOn($html, '2026-10-08'))->toBe('1 / 3')
        ->and(availabilityOn($html, '2026-10-09'))->toBe('1 / 3')
        ->and(availabilityOn($html, '2026-10-10'))->toBe('3 / 3');
});

it('leaves availability untouched while a booking is still unpaid', function (): void {
    seedRentalItem('r1', ['name' => 'Folding chair', 'units' => 3]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'dateFrom' => '2026-10-08',
        'dateTo' => '2026-10-09',
        'qty' => 3,
        'status' => 'awaiting_payment',
    ]);

    $html = $this->get('/rentals')->assertOk()->getContent();

    expect(availabilityOn($html, '2026-10-08'))->toBe('3 / 3');
});

it('serves the catalog from the shared availability cache', function (): void {
    seedRentalItem('r1');

    $this->get('/rentals')->assertOk();

    expect(Cache::has(AvailabilityCache::RENTALS_KEY))->toBeTrue();
});

it('reads the overlapping bookings once for the whole catalog, not per item', function (): void {
    seedRentalItem('r1');
    seedRentalItem('r2');
    seedRentalItem('r3');

    $client = new RecordingRtdbClient(app(RtdbClient::class));
    app()->instance(RtdbClient::class, $client);

    app(RentalCatalog::class)->entries(now());

    // Three items, one window: the availability read is shared, so the booking
    // tree is queried once rather than once per item (rule 3).
    $queries = array_filter(
        $client->queries,
        static fn(array $query): bool => $query['path'] === 'rentalBookings',
    );

    expect($queries)->toHaveCount(1);
});

it('evicts the rental availability cache on a booking write', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);

    $this->get('/rentals')->assertOk();

    expect(Cache::has(AvailabilityCache::RENTALS_KEY))->toBeTrue();

    bookRental('r1', '2026-10-08', '2026-10-10', 2);

    expect(Cache::has(AvailabilityCache::RENTALS_KEY))->toBeFalse();
});

it('respects the open/closed badge and disables booking while closed', function (): void {
    seedSettings([
        'hours' => weekdayHours(),
        'forceClose' => true,
        'gcash' => ['number' => '0917 123 4567', 'qr' => 'data:image/jpeg;base64,QRDATA'],
    ]);
    seedRentalItem('r1', ['name' => 'Folding chair']);

    $this->get('/rentals')
        ->assertOk()
        ->assertSee('Closed')
        ->assertSee('Ordering is closed right now.')
        ->assertSee('<button class="primary-button" type="button" disabled', false);
});

it('offers a booking form with a date range and a quantity stepper', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['name' => 'Folding chair']);

    $this->get('/rentals/r1')
        ->assertOk()
        ->assertSee('name="dateFrom"', false)
        ->assertSee('name="dateTo"', false)
        ->assertSee('data-stepper="qty"', false);

    expect(visibleFieldNames($this->get('/rentals/r1')->getContent()))->toBe(['dateFrom', 'dateTo', 'qty']);
});

// --- Booking --------------------------------------------------------------

it('requires sign-in to book a rental', function (): void {
    seedRentalItem('r1');

    bookRental('r1', '2026-10-08', '2026-10-09')->assertRedirect(route('login'));

    expect(rtdb()->get('rentalBookings'))->toBeNull();
});

it('refuses a booking POST while the store is closed', function (): void {
    seedSettings([
        'hours' => weekdayHours(),
        'forceClose' => true,
        'gcash' => ['number' => '0917 123 4567', 'qr' => 'data:image/jpeg;base64,QRDATA'],
    ]);
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);

    bookRental('r1', '2026-10-08', '2026-10-09')->assertForbidden();

    expect(rtdb()->get('rentalBookings'))->toBeNull();
});

it('refuses a booking until the GCash payment block exists', function (): void {
    seedSettings(['hours' => weekdayHours()]);
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);

    $this->get('/rentals/r1')->assertOk()->assertSee('GCash number and QR');

    bookRental('r1', '2026-10-08', '2026-10-09')->assertForbidden();

    expect(rtdb()->get('rentalBookings'))->toBeNull();
});

it('keeps the item page browsable while signed out, prompting the Google button to book', function (): void {
    seedRentalItem('r1', ['name' => 'Folding chair']);

    $this->get('/rentals/r1')
        ->assertOk()
        ->assertSee('Folding chair')
        ->assertSee('Sign in with Google to book');
});

it('computes the total server-side and opens a 15-minute GCash window', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['name' => 'Folding chair', 'pricePerDay' => 50.0, 'units' => 5]);

    bookRental('r1', '2026-10-08', '2026-10-10', 2)->assertRedirect();

    $bookings = rtdb()->get('rentalBookings');

    expect($bookings)->toBeArray()->toHaveCount(1);

    $id = (string) array_key_first($bookings);
    $node = $bookings[$id];

    // 50/day × 3 days × 2 units = 300.
    expect($node['itemId'])->toBe('r1')
        ->and($node['uid'])->toBe('sub-ada')
        ->and($node['qty'])->toBe(2)
        ->and($node['dateFrom'])->toBe('2026-10-08')
        ->and($node['dateTo'])->toBe('2026-10-10')
        ->and($node['pricePerDay'])->toBe(50.0)
        ->and($node['total'])->toBe(300.0)
        ->and($node['status'])->toBe(RentalStatus::AwaitingPayment->value)
        ->and($node['code'])->toBeString()->toStartWith('R');

    $placedAt = Carbon::parse($node['placedAt']);
    $deadlineAt = Carbon::parse($node['deadlineAt']);

    expect($placedAt->diffInSeconds($deadlineAt))->toBe(900.0);
});

it('shows the booking tracker with the code, GCash frame, and the proof surface', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['name' => 'Folding chair', 'pricePerDay' => 50.0, 'units' => 5]);

    bookRental('r1', '2026-10-08', '2026-10-10', 2);

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);
    $code = (string) $bookings[$id]['code'];

    $this->get(route('rentals.track', ['booking' => $id]))
        ->assertOk()
        ->assertSee($code)
        ->assertSee('0917 123 4567')
        ->assertSee('data:image/jpeg;base64,QRDATA', false)
        ->assertSee('Attach payment screenshot')
        ->assertSee('data-proof-upload', false);

    $this->get(route('rentals.state', ['booking' => $id]))
        ->assertOk()
        ->assertJson([
            'code' => $code,
            'status' => 'awaiting_payment',
            'total' => 300.0,
        ]);
});

it('does not let one customer view another account\'s rental booking', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);
    bookRental('r1', '2026-10-08', '2026-10-09');

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);

    auth()->logout();
    signIn('grace@example.com', 'Grace Hopper', 'sub-grace');

    $this->get(route('rentals.track', ['booking' => $id]))->assertForbidden();
    $this->get(route('rentals.state', ['booking' => $id]))->assertForbidden();
});

it('blocks a rental range that overlaps a confirmed booking', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['name' => 'Folding chair', 'units' => 2]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'dateFrom' => '2026-10-08',
        'dateTo' => '2026-10-10',
        'qty' => 2,
        'status' => 'confirmed',
    ]);

    bookRental('r1', '2026-10-09', '2026-10-11', 1)
        ->assertSessionHasErrors('rental');

    // The overlapping attempt wrote nothing; only the seeded booking remains.
    expect(rtdb()->get('rentalBookings'))->toHaveCount(1);
});

it('blocks a range whose quantity exceeds the item\'s units', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['name' => 'Folding chair', 'units' => 2]);

    bookRental('r1', '2026-10-08', '2026-10-09', 3)->assertSessionHasErrors('rental');

    expect(rtdb()->get('rentalBookings'))->toBeNull();
});

it('auto-verifies a rental proof when the amount matches and the ref# is present', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);
    bookRental('r1', '2026-10-08', '2026-10-10', 2);

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);

    $this->post(route('rentals.proof.start', ['booking' => $id]))->assertNoContent();

    $this->post(route('rentals.proof.store', ['booking' => $id]), [
        'image' => rentalProofUpload(),
        'amount' => '300.00',
        'ref' => 'GC1234567890',
    ])->assertNoContent();

    $node = rtdb()->get("rentalBookings/{$id}");

    expect($node['status'])->toBe(RentalStatus::Confirmed->value)
        ->and($node['verifiedAt'])->toBeString()
        ->and($node['payments'])->toBe([['method' => 'gcash', 'amount' => 300.0]])
        ->and($node['proofInFlight'])->toBeFalse();

    // The proof lives in the sibling node, never on the booking node.
    $proof = rtdb()->get("proofs/rental-{$id}");

    expect($proof)->toBeArray()->and($proof['imageBase64'])->toBeString();
});

it('flags a hold when the rental proof amount mismatches', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);
    bookRental('r1', '2026-10-08', '2026-10-10', 2);

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);

    $this->post(route('rentals.proof.store', ['booking' => $id]), [
        'image' => rentalProofUpload(),
        'amount' => '250.00',
        'ref' => 'GC1234567890',
    ])->assertNoContent();

    $node = rtdb()->get("rentalBookings/{$id}");

    // A mismatch is its own flagged-hold status, so the counter can resolve it
    // and the lapse sweep can retire it — never stuck on awaiting_payment.
    expect($node['status'])->toBe(RentalStatus::Verifying->value)
        ->and($node['enteredAmount'])->toBe(250.0)
        ->and($node['gcashRef'])->toBe('GC1234567890');
});

it('never writes a rental booking into the order stream or sales', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);
    bookRental('r1', '2026-10-08', '2026-10-10', 2);

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);

    $this->post(route('rentals.proof.store', ['booking' => $id]), [
        'image' => rentalProofUpload(),
        'amount' => '300.00',
        'ref' => 'GC1234567890',
    ])->assertNoContent();

    // A rental is a separate stream: no order row, no sale row, and the kitchen
    // board never sees it.
    expect(rtdb()->get('orders'))->toBeNull()
        ->and(rtdb()->get('sales'))->toBeNull()
        ->and(onKitchenBoard())->toBe([]);
});

// --- Counter ops ----------------------------------------------------------

it('lowers stock on hand over and raises it on confirm return', function (): void {
    seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('cashier@example.com', 'Maria Santos');

    seedRentalItem('r1', ['name' => 'Folding chair', 'units' => 3]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'qty' => 2,
        'status' => RentalStatus::Confirmed->value,
    ]);

    $this->post(route('cashier.rentals.hand-over', ['booking' => 'b1']))->assertRedirect();

    expect(rtdb()->get('rentals/r1')['units'])->toBe(1)
        ->and(rtdb()->get('rentalBookings/b1')['status'])->toBe(RentalStatus::HandedOver->value)
        ->and(rtdb()->get('rentalBookings/b1')['handledBy']['name'])->toBe('Maria Santos');

    $this->post(route('cashier.rentals.confirm-return', ['booking' => 'b1']))->assertRedirect();

    expect(rtdb()->get('rentals/r1')['units'])->toBe(3)
        ->and(rtdb()->get('rentalBookings/b1')['status'])->toBe(RentalStatus::Returned->value);
});

// --- Flagged rental proofs (counter resolves the exception) ---------------

it('surfaces a flagged rental booking with one-tap Confirm and Cancel', function (): void {
    seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('cashier@example.com', 'Maria Santos');

    seedRentalItem('r1', ['units' => 3]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'status' => RentalStatus::Verifying->value,
        'enteredAmount' => 250.0,
        'gcashRef' => 'GC999',
    ]);

    $this->get(route('cashier.rentals.index'))
        ->assertOk()
        ->assertSee('Flagged proofs')
        ->assertSee('RNT1')
        ->assertSee('Confirm')
        ->assertSee('Cancel');
});

it('confirms a flagged rental proof to confirmed with stock untouched', function (): void {
    seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('cashier@example.com', 'Maria Santos');

    seedRentalItem('r1', ['units' => 3]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'status' => RentalStatus::Verifying->value,
        'enteredAmount' => 250.0,
        'gcashRef' => 'GC999',
    ]);

    $this->post(route('cashier.rentals.confirm-proof', ['booking' => 'b1']))->assertRedirect();

    $booking = rtdb()->get('rentalBookings/b1');

    expect($booking['status'])->toBe(RentalStatus::Confirmed->value)
        ->and($booking['verifiedAt'])->toBeString()
        ->and($booking['enteredAmount'])->toBeNull()
        ->and($booking['handledBy']['name'])->toBe('Maria Santos')
        // The units were never handed over, so stock is untouched.
        ->and(rtdb()->get('rentals/r1')['units'])->toBe(3);
});

it('cancels a flagged rental booking with stock untouched', function (): void {
    seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('cashier@example.com', 'Maria Santos');

    seedRentalItem('r1', ['units' => 3]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'status' => RentalStatus::Verifying->value,
        'enteredAmount' => 250.0,
        'gcashRef' => 'GC999',
    ]);

    $this->post(route('cashier.rentals.cancel', ['booking' => 'b1']))->assertRedirect();

    expect(rtdb()->get('rentalBookings/b1')['status'])->toBe(RentalStatus::Cancelled->value)
        ->and(rtdb()->get('rentals/r1')['units'])->toBe(3);
});

it('lapses a flagged rental booking whose deadline passes unresolved', function (): void {
    seedRentalItem('r1', ['units' => 5]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'qty' => 2,
        'status' => RentalStatus::Verifying->value,
        'deadlineAt' => '2026-10-06T02:15:00Z',
        'enteredAmount' => 250.0,
        'proofReceivedAt' => '2026-10-06T02:14:00Z',
    ]);

    $this->travel(16)->minutes();

    $this->artisan('rentals:lapse')->assertSuccessful();

    // A flagged booking has proofReceivedAt set, so only the explicit flagged
    // status lets the sweep retire it — stock stays untouched.
    expect(rtdb()->get('rentalBookings/b1')['status'])->toBe(RentalStatus::Lapsed->value)
        ->and(rtdb()->get('rentals/r1')['units'])->toBe(5);
});

it('shows a quiet overdue staff row for a rental past its return-due date, with no penalty', function (): void {
    seedStaff('cashier@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('cashier@example.com', 'Maria Santos');

    seedRentalItem('r1', ['units' => 3]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'qty' => 1,
        'status' => RentalStatus::HandedOver->value,
        'returnDueAt' => '2026-10-05T15:59:59Z',
    ]);

    $this->get(route('cashier.rentals.index'))
        ->assertOk()
        ->assertSee('Overdue')
        ->assertSee('RNT1');

    // No auto-penalty: the booking carries no penalty field and stock is not
    // silently written down.
    expect(rtdb()->get('rentalBookings/b1'))->not->toHaveKey('penalty')
        ->and(rtdb()->get('rentals/r1')['units'])->toBe(3);
});

it('lets an admin create a rental item with a server-resized image', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com');

    $this->post('/admin/rentals', [
        'name' => 'Folding chair',
        'pricePerDay' => '50',
        'units' => '4',
        'image' => UploadedFile::fake()->image('chair.jpg', 1600, 1200),
    ])->assertRedirect(route('admin.rentals.index'));

    $items = rtdb()->get('rentals');

    expect($items)->toBeArray()->toHaveCount(1);

    $node = $items[(string) array_key_first($items)];

    expect($node)->toMatchArray([
        'name' => 'Folding chair',
        'pricePerDay' => 50.0,
        'units' => 4,
    ]);

    $bytes = base64_decode((string) $node['imageBase64'], true);

    expect($bytes)->not->toBeFalse();

    $info = getimagesizefromstring((string) $bytes);

    expect($info)->not->toBeFalse()
        ->and($info[2])->toBe(IMAGETYPE_JPEG)
        ->and(max($info[0], $info[1]))->toBeLessThanOrEqual(800);
});

it('adjusts stock with inline steppers and an Add stock receiving entry', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com');

    seedRentalItem('r1', ['name' => 'Folding chair', 'units' => 2]);

    // The inline + / − stepper adjusts on-hand units.
    $this->post(route('admin.rentals.adjust', ['item' => 'r1']), ['delta' => 1])->assertRedirect();

    expect(rtdb()->get('rentals/r1')['units'])->toBe(3);

    // Add stock is a receiving entry: supplier · qty · date.
    $this->post(route('admin.rentals.stock', ['item' => 'r1']), [
        'supplier' => 'Cebu Trading',
        'qty' => '5',
        'date' => '2026-10-06',
    ])->assertRedirect();

    expect(rtdb()->get('rentals/r1')['units'])->toBe(8);

    $stock = rtdb()->get('rentals/r1/stock');

    expect($stock)->toBeArray()->toHaveCount(1);
});

it('shows the low-stock list with a restock button', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com');

    seedRentalItem('r1', ['name' => 'Folding chair', 'units' => 1]);
    seedRentalItem('r2', ['name' => 'Banquet table', 'units' => 10]);

    $this->get(route('admin.rentals.index'))
        ->assertOk()
        ->assertSee('Low stock')
        ->assertSee('Folding chair')
        ->assertSee('Restock');
});

it('renders the Add stock quantity as a stepper with the date defaulting today', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com');

    seedRentalItem('r1', ['name' => 'Folding chair', 'units' => 1]);

    $this->get(route('admin.rentals.index'))
        ->assertOk()
        // The quantity stepper adjusts a number input, not a free-text field.
        ->assertSee('data-stock-step="-1"', false)
        ->assertSee('data-stock-step="1"', false)
        ->assertSee('name="qty"', false)
        // The receiving date defaults today.
        ->assertSee('value="2026-10-06"', false);
});

it('defaults an Add stock date to today when the admin leaves it blank', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com');

    seedRentalItem('r1', ['units' => 2]);

    $this->post(route('admin.rentals.stock', ['item' => 'r1']), [
        'supplier' => 'Cebu Trading',
        'qty' => '3',
    ])->assertRedirect();

    $stock = rtdb()->get('rentals/r1/stock');
    $entry = $stock[(string) array_key_first($stock)];

    expect($entry['date'])->toBe('2026-10-06')
        ->and(rtdb()->get('rentals/r1')['units'])->toBe(5);
});

it('edits a rental item, keeping the stored image when none is uploaded', function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com');

    seedRentalItem('r1', ['name' => 'Folding chair', 'pricePerDay' => 50.0, 'units' => 3, 'imageBase64' => 'STORED']);

    $this->put(route('admin.rentals.update', ['item' => 'r1']), [
        'name' => 'Folding chair (large)',
        'pricePerDay' => '60',
        'units' => '4',
    ])->assertRedirect(route('admin.rentals.index'));

    expect(rtdb()->get('rentals/r1'))->toMatchArray([
        'name' => 'Folding chair (large)',
        'pricePerDay' => 60.0,
        'units' => 4,
        'imageBase64' => 'STORED',
    ]);
});

// --- Regression: never in the order stream --------------------------------

it('lapses an unpaid booking with stock untouched', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);
    bookRental('r1', '2026-10-08', '2026-10-10', 2);

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);

    $this->travel(16)->minutes();

    $this->artisan('rentals:lapse')->assertSuccessful();

    expect(rtdb()->get("rentalBookings/{$id}")['status'])->toBe(RentalStatus::Lapsed->value)
        ->and(rtdb()->get("rentalBookings/{$id}")['lapsedAt'])->toBeString()
        // Stock was never moved — units only fall at handover.
        ->and(rtdb()->get('rentals/r1')['units'])->toBe(5);
});

it('holds a booking whose proof upload is mid-flight past its deadline', function (): void {
    seedRentalItem('r1', ['units' => 5]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'status' => RentalStatus::AwaitingPayment->value,
        'deadlineAt' => '2026-10-06T02:15:00Z',
        'proofInFlight' => true,
    ]);

    $this->artisan('rentals:lapse')->assertSuccessful();

    expect(rtdb()->get('rentalBookings/b1')['status'])->toBe(RentalStatus::AwaitingPayment->value);
});

it('does not lapse a booking that already received a proof', function (): void {
    seedRentalItem('r1', ['units' => 5]);
    seedRentalBooking('b1', [
        'itemId' => 'r1',
        'status' => RentalStatus::AwaitingPayment->value,
        'deadlineAt' => '2026-10-06T02:15:00Z',
        'proofReceivedAt' => '2026-10-06T02:14:00Z',
    ]);

    $this->artisan('rentals:lapse')->assertSuccessful();

    expect(rtdb()->get('rentalBookings/b1')['status'])->toBe(RentalStatus::AwaitingPayment->value);
});

it('runs the rental lapse sweep on the Laravel scheduler in Asia/Manila', function (): void {
    $this->artisan('schedule:list')->assertSuccessful();

    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->keyBy(fn($event): string => (string) $event->description);

    expect($events->has('rentals:lapse'))->toBeTrue();

    $lapse = $events->get('rentals:lapse');

    expect($lapse->expression)->toBe('* * * * *')
        ->and($lapse->timezone)->toBe('Asia/Manila');
});

it('wires the rental tracker to the same auto-upload proof client as an order', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);
    bookRental('r1', '2026-10-08', '2026-10-10', 2);

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);

    $html = $this->get(route('rentals.track', ['booking' => $id]))->assertOk()->getContent();

    // The same gate as an order: auto-upload on selection, no Submit control,
    // the same compression bounds, one automatic retry, and the in-flight marker.
    expect($html)->toBeString()
        ->and($html)->toContain("input.addEventListener('change', begin)")
        ->and($html)->not->toContain('name="submit-proof"')
        ->and($html)->toContain('var MAX_BYTES = 300 * 1024')
        ->and($html)->toContain('var FLOOR_EDGE = 720')
        ->and($html)->toContain("canvas.toDataURL('image/jpeg'")
        ->and($html)->toContain('if (attempt < 1)')
        ->and($html)->toContain('Upload failed — tap to retry')
        ->and($html)->toContain('markInFlight()');
});

it('gives the rental tracker poll a pause control (WCAG 2.2 SC 2.2.2)', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);
    bookRental('r1', '2026-10-08', '2026-10-10', 2);

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);

    $html = $this->get(route('rentals.track', ['booking' => $id]))->assertOk()->getContent();

    expect($html)->toBeString()
        ->and($html)->toContain('id="tracker-pause"')
        ->and($html)->toContain('Pause updates')
        ->and($html)->toContain('aria-pressed="false"')
        ->and($html)->toContain('setPaused(!paused)');
});

it('coalesces rental tracker polls so N screens cost one read per cadence', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);
    bookRental('r1', '2026-10-08', '2026-10-10', 2);

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);

    $client = useCountingRtdb();

    $this->get(route('rentals.state', ['booking' => $id]))->assertOk();
    $this->get(route('rentals.state', ['booking' => $id]))->assertOk();

    expect($client->reads["rentalBookings/{$id}"] ?? 0)->toBe(1);

    $this->travel(\App\Rentals\RentalTracker::POLL_SECONDS + 1)->seconds();

    $this->get(route('rentals.state', ['booking' => $id]))->assertOk();

    expect($client->reads["rentalBookings/{$id}"] ?? 0)->toBe(2);
});

it('never lets a rental booking reach the kitchen board or Top 3', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    seedRentalItem('r1', ['units' => 5]);
    bookRental('r1', '2026-10-08', '2026-10-10', 2);

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);

    $this->post(route('rentals.proof.store', ['booking' => $id]), [
        'image' => rentalProofUpload(),
        'amount' => '300.00',
        'ref' => 'GC1234567890',
    ])->assertNoContent();

    expect(onKitchenBoard())->toBe([])
        ->and(rtdb()->get('sales'))->toBeNull()
        ->and(rtdb()->get('stats/top3'))->toBeNull();
});
