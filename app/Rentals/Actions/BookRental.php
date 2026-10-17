<?php

declare(strict_types=1);

namespace App\Rentals\Actions;

use App\Auth\RtdbUser;
use App\Cache\AvailabilityCache;
use App\Orders\PaymentWindow;
use App\Rentals\RentalAvailability;
use App\Rentals\RentalBlocked;
use App\Rentals\RentalBooking;
use App\Rentals\RentalCode;
use App\Rentals\RentalPeriod;
use App\Rentals\RentalStatus;
use App\Rentals\RentalTotal;
use App\Rentals\Rentals;
use App\Rtdb\Contracts\RtdbClient;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Places a rental booking (PLAN §4 *Rentals* — booking).
 *
 * The availability is **re-checked server-side** before the write (the public
 * catalog cache may be stale), so an overlapping range is blocked with a reason
 * and nothing is written. A clean booking is stored **awaiting payment** with
 * the frozen per-day rate, the computed total, and a 15-minute window
 * (`deadlineAt = placedAt + 15 min`, the same {@see PaymentWindow} orders use).
 *
 * Stock is **untouched** at booking: units only move at handover. An unpaid
 * booking lapses with stock untouched, and the total is prepaid in full — no
 * deposit split.
 *
 * The write evicts the shared availability cache so the catalog reflects the
 * reservation on the next request. The booking is a separate stream from
 * orders: no `orders` row, no `sales/{id}` row.
 */
final readonly class BookRental
{
    public function __construct(
        private RtdbClient $client,
        private Rentals $items,
        private RentalAvailability $availability,
        private AvailabilityCache $cache,
    ) {}

    public function __invoke(RtdbUser $customer, string $itemId, RentalPeriod $period, int $qty): RentalBooking
    {
        $item = $this->items->find($itemId);

        if ($item === null) {
            throw RentalBlocked::withReason('That rental item no longer exists.');
        }

        if ($period->from->lessThan(CarbonImmutable::instance(now(BusinessTime::timezone()))->startOfDay())) {
            throw RentalBlocked::withReason('Pick a pickup date from today onward.');
        }

        if ($period->days() > RentalPeriod::maxDays()) {
            throw RentalBlocked::withReason('That rental range is too long — book at most ' . RentalPeriod::maxDays() . ' days.');
        }

        if ($qty < 1 || $qty > $item->units) {
            throw RentalBlocked::withReason("Only {$item->units} unit(s) of {$item->name} exist.");
        }

        if (! $this->availability->isAvailable($item, $period, $qty)) {
            throw RentalBlocked::withReason("That range is not available for {$item->name} — pick different dates or fewer units.");
        }

        $id = (string) Str::ulid();
        $placedAt = now('UTC');
        $total = RentalTotal::for($item, $period, $qty);

        $this->client->set("rentalBookings/{$id}", [
            'code' => RentalCode::draw()->value,
            'itemId' => $item->id,
            'itemName' => $item->name,
            'uid' => $customer->sub,
            'dateFrom' => $period->fromString(),
            'dateTo' => $period->toString(),
            'qty' => $qty,
            'pricePerDay' => $item->pricePerDay,
            'total' => $total,
            'status' => RentalStatus::AwaitingPayment->value,
            'payments' => [],
            'placedAt' => $placedAt->toIso8601ZuluString(),
            'deadlineAt' => PaymentWindow::deadlineFrom($placedAt)->toIso8601ZuluString(),
            'returnDueAt' => self::returnDue($period)->toIso8601ZuluString(),
            'createdAt' => $placedAt->toIso8601ZuluString(),
        ]);

        // Evict the shared availability entry so the reservation propagates on
        // the next catalog read. Rentals are a separate stream: no order row,
        // no `sales/{id}` row — the kitchen board and Top 3 never see them.
        $this->cache->forgetRentals();

        $node = $this->client->get("rentalBookings/{$id}");

        if (! is_array($node)) {
            throw new \RuntimeException("The placed rental booking [{$id}] could not be read back.");
        }

        $booking = RentalBooking::fromNode($id, $node);

        if ($booking === null) {
            throw new \RuntimeException("The placed rental booking [{$id}] could not be read back.");
        }

        return $booking;
    }

    /**
     * The instant a booking is due back: the end of the return date in business
     * time, so the quiet overdue row compares against a real wall-clock due.
     */
    private static function returnDue(RentalPeriod $period): CarbonImmutable
    {
        return $period->to->setTimezone(BusinessTime::timezone())->endOfDay();
    }
}
