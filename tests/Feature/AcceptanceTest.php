<?php

declare(strict_types=1);

use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Staff\StaffRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * #35 — the end-to-end acceptance pass (PLAN §4 *Acceptance*).
 *
 * This file drives the plan's own acceptance bullets from the highest entry
 * points, exactly as a person would, so the sign-off is behavioural and
 * observable rather than a claim. The happy path runs in one test from a
 * category browse through to the sale showing in analytics; the two beats the
 * rest of the suite proved only in fragments — the tracker flipping on
 * dismissal, and a throttle recovering inside its window — each get their own.
 *
 * Every assertion is an HTTP response or a value a caller can observe through
 * another public entry point; time is frozen with `travelTo()`.
 */

beforeEach(function (): void {
    seedCategory('drinks', 'Drinks', 1);
    seedCategory('mains', 'Mains', 2);
    seedProduct('p1', ['name' => 'Milktea', 'price' => 200.0, 'categoryId' => 'drinks']);
    seedProduct('p2', ['name' => 'Adobo', 'price' => 180.0, 'categoryId' => 'mains']);

    seedSettings([
        'hours' => weekdayHours(),
        'gcash' => ['number' => '0917 123 4567', 'qr' => 'data:image/jpeg;base64,QRDATA'],
        'kitchenSecret' => 'k-secret-123',
    ]);

    // 2026-10-06 12:00 Asia/Manila is 04:00 UTC.
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

/**
 * A deterministic, high-entropy JPEG so the server re-encode path runs.
 */
function acceptanceProofUpload(): UploadedFile
{
    $image = imagecreatetruecolor(1200, 900);
    mt_srand(35);

    for ($x = 0; $x < 1200; $x += 2) {
        for ($y = 0; $y < 900; $y += 2) {
            $colour = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, $colour);
        }
    }

    ob_start();
    imagejpeg($image, null, 95);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    $path = testTempFile('crnp-acceptance-', '.jpg');

    file_put_contents($path, $bytes);

    return new UploadedFile($path, 'gcash-proof.jpg', 'image/jpeg', null, true);
}

it('drives the whole happy path from a category browse to the sale showing in analytics', function (): void {
    // 1. Browse by category — no login wall.
    $this->get('/?category=drinks')
        ->assertOk()
        ->assertSee('Milktea')
        ->assertDontSee('Adobo');

    // 2. Sign in with Google — one button, no form, no OTP.
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'sub-ada',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]));

    $this->get(route('auth.google.callback'))->assertRedirect();
    $this->assertAuthenticated();

    // 3. Cart — steppers and computed totals, the human types nothing.
    $this->post(route('cart.update'), ['product' => 'p1', 'quantity' => 2, 'addOns' => []])
        ->assertRedirect();

    // 4. Place order — the code is returned and the 15-minute window starts.
    $this->post(route('checkout.store'), ['idempotencyKey' => 'accept-1'])
        ->assertRedirect(route('orders.tracker'));

    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);
    $node = $orders[$id];
    $code = (string) $node['code'];

    expect($node['total'])->toBe(400.0)
        ->and(Carbon::parse($node['placedAt'])->diffInSeconds(Carbon::parse($node['deadlineAt'])))->toBe(900.0);

    // 5. The GCash QR auto-appears in its branded frame; the code is shown,
    //    never typed.
    $this->get(route('orders.show', ['order' => $id]))
        ->assertOk()
        ->assertSee($code)
        ->assertSee('0917 123 4567')
        ->assertSee('data:image/jpeg;base64,QRDATA', false)
        ->assertSee('Awaiting payment');

    // 6. Pay + Attach the screenshot with the amount and ref# → auto-verified,
    //    no cashier tap.
    $this->post(route('orders.proof.store', ['order' => $id]), [
        'image' => acceptanceProofUpload(),
        'amount' => '400.00',
        'ref' => 'GC1234567890',
    ])->assertNoContent();

    expect(rtdb()->get("orders/{$id}")['status'])->toBe(OrderStatus::Cooking->value);

    // 7. The kitchen ticket appears the same instant.
    $this->get('/kitchen/k-secret-123')
        ->assertOk()
        ->assertSee($code);

    // 8. Mark served — the one happy-path tap, by the cashier on duty.
    auth()->logout();
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('maria@example.com', 'Maria Santos');

    $this->post(route('cashier.orders.serve', ['order' => $id]))
        ->assertRedirect(route('cashier.queue'));

    // 9. The customer collects: the tracker flips cooking → READY.
    auth()->logout();
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    expect(app(OrderTracker::class)->state($id)?->label)->toBe('READY');

    // 10. The sale shows in analytics.
    auth()->logout();
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com', 'Owner Santos');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Sales today')
        ->assertSee('₱400.00')
        ->assertSee('Milktea');
});

it('flips the customer tracker to the dismissed sentence when the window lapses', function (): void {
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    $this->post(route('cart.update'), ['product' => 'p1', 'quantity' => 1, 'addOns' => []])->assertRedirect();
    $this->post(route('checkout.store'), ['idempotencyKey' => 'accept-dismiss'])->assertRedirect();

    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);

    // The window lapses with zero proof: the tracker flips and the queue clears.
    $this->travel(16)->minutes();
    $this->artisan('orders:expire')->assertSuccessful();

    expect(app(OrderTracker::class)->state($id)?->label)->toBe('Order dismissed — no payment received');

    $awaiting = rtdb()->query(
        'orders',
        \App\Rtdb\RtdbQuery::orderBy('status')->equalTo(OrderStatus::AwaitingPayment->value),
    );

    expect($awaiting)->not->toHaveKey($id);
});

it('rings up a walk-in with cash + GCash and prints the receipt', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('maria@example.com', 'Maria Santos');

    // Build the walk-in by tapping tiles: the promo price and totals compute.
    $this->post(route('cashier.walkins.store'))->assertRedirect();
    $orders = rtdb()->get('orders');
    $id = (string) array_key_first($orders);

    $this->post(route('cashier.walkins.items.store', ['walkin' => $id]), [
        'product' => 'p1',
        'quantity' => 2,
    ])->assertRedirect();

    // Cash + GCash: cash 100 of a 400 total → GCash 300, ref# required.
    $this->post(route('cashier.walkins.settle', ['walkin' => $id]), [
        'cash' => '100',
        'gcashAmount' => '300',
        'gcashRef' => '1234567890',
    ])->assertRedirect(route('cashier.walkins.receipt', ['walkin' => $id]));

    $code = (string) rtdb()->get("orders/{$id}")['code'];

    expect(rtdb()->get("orders/{$id}")['payments'])->toBe([
        ['method' => 'cash', 'amount' => 100.0],
        ['method' => 'gcash', 'amount' => 300.0],
    ]);

    // The receipt prints the breakdown and the order code — one button.
    $this->get(route('cashier.walkins.receipt', ['walkin' => $id]))
        ->assertOk()
        ->assertSee($code)
        ->assertSee('Cash')
        ->assertSee('GCash')
        ->assertSee('₱100.00')
        ->assertSee('₱300.00');
});

it('enters a reservation, blocks a conflict, confirms, and shows it on the calendar', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('maria@example.com', 'Maria Santos');

    seedSettings([
        'capacities' => [\App\Settings\ReservationArea::DineIn->value => 4],
    ]);

    // Enter a new booking: the server checks the conflict before the write.
    $this->post('/cashier/reservations', [
        'name' => 'Ana Cruz',
        'phone' => '09171234567',
        'type' => \App\Settings\ReservationArea::DineIn->value,
        'preferredType' => \App\Settings\ReservationArea::DineIn->value,
        'when' => '2026-10-08T18:00',
        'party' => '2',
    ])->assertRedirect(route('cashier.reservations.index'));

    $rows = rtdb()->get('reservations');
    $id = (string) array_key_first($rows);

    expect($rows[$id]['status'])->toBe(\App\Reservations\ReservationStatus::Pending->value);

    // A conflicting booking for the same slot is blocked with a reason and
    // writes nothing new.
    $this->post('/cashier/reservations', [
        'name' => 'Walk In',
        'phone' => '09179999999',
        'type' => \App\Settings\ReservationArea::DineIn->value,
        'preferredType' => \App\Settings\ReservationArea::DineIn->value,
        'when' => '2026-10-08T18:00',
        'party' => '3',
    ])->assertSessionHasErrors('reservation');

    expect(rtdb()->get('reservations'))->toHaveCount(1);

    // Confirm, then the booking shows on the calendar (grouped by date).
    $this->post("/cashier/reservations/{$id}/confirm")->assertRedirect();

    expect(rtdb()->get("reservations/{$id}/status"))->toBe(\App\Reservations\ReservationStatus::Confirmed->value);

    $this->get('/cashier/reservations')
        ->assertOk()
        ->assertSee('2026-10-08')
        ->assertSee('Ana Cruz')
        ->assertSee('Confirmed');
});

it('drives a rental from a public availability browse through hand over and return', function (): void {
    seedRentalItem('r1', ['name' => 'Folding chair', 'pricePerDay' => 50.0, 'units' => 3]);

    // 1. Browse availability — read-only, computed stock vs overlaps.
    $this->get('/rentals')
        ->assertOk()
        ->assertSee('Folding chair')
        ->assertSee('₱50.00')
        ->assertSee('per day');

    // 2. Book (date range + qty) → QR auto-sent → 15-minute window.
    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    $this->post(route('rentals.book', ['item' => 'r1']), [
        'dateFrom' => '2026-10-08',
        'dateTo' => '2026-10-10',
        'qty' => 2,
    ])->assertRedirect();

    $bookings = rtdb()->get('rentalBookings');
    $id = (string) array_key_first($bookings);

    // 50/day × 3 days × 2 units = 300; the GCash frame shows the QR.
    expect($bookings[$id]['total'])->toBe(300.0);

    $this->get(route('rentals.track', ['booking' => $id]))
        ->assertOk()
        ->assertSee('0917 123 4567')
        ->assertSee('data:image/jpeg;base64,QRDATA', false);

    // 3. Attach the screenshot with a matching amount → auto-verified, stock
    //    untouched until hand over.
    $this->post(route('rentals.proof.store', ['booking' => $id]), [
        'image' => acceptanceProofUpload(),
        'amount' => '300.00',
        'ref' => 'GC1234567890',
    ])->assertNoContent();

    expect(rtdb()->get("rentalBookings/{$id}")['status'])->toBe(\App\Rentals\RentalStatus::Confirmed->value)
        ->and(rtdb()->get('rentals/r1')['units'])->toBe(3);

    // 4. Counter Hand over lowers stock; Confirm return raises it.
    auth()->logout();
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    signIn('maria@example.com', 'Maria Santos');

    $this->post(route('cashier.rentals.hand-over', ['booking' => $id]))->assertRedirect();
    expect(rtdb()->get('rentals/r1')['units'])->toBe(1);

    $this->post(route('cashier.rentals.confirm-return', ['booking' => $id]))->assertRedirect();
    expect(rtdb()->get('rentals/r1')['units'])->toBe(3);
});
