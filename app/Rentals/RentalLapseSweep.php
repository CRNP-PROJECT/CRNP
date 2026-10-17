<?php

declare(strict_types=1);

namespace App\Rentals;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The **rental lapse** sweep (PLAN §4 *Rentals* — booking: "an unpaid booking
 * lapses with stock untouched").
 *
 * Runs every minute against the indexed `deadlineAt = placedAt + 15 min` and
 * lapses bookings that are either **zero-proof and not mid-upload** — an upload
 * sent with no terminal response holds the booking across 15:00, so a customer
 * is never lapsed for a connection they could not see failing — **or a flagged
 * hold** (`verifying`): a proof whose amount/ref mismatched reaches the counter,
 * but if no human resolves it before the deadline the booking lapses rather than
 * being stuck forever (a flagged booking has `proofReceivedAt` set, so the
 * zero-proof test alone would never lapse it). Because stock only moves at
 * handover, a lapse **leaves stock untouched**.
 *
 * The read is floored rather than opened to the epoch, so the every-minute
 * query stays bounded to recently-lapsed windows (PLAN §4 *Quota discipline*,
 * rule 3); the documented manual fallback widens the lookback to recover from a
 * scheduler outage.
 */
final readonly class RentalLapseSweep
{
    /**
     * The default lookback for the `deadlineAt` range. Only bounds the read —
     * a booking lapses within one interval of its deadline.
     */
    public const DEFAULT_LOOKBACK_HOURS = 24;

    public function __construct(
        private RtdbClient $client,
        private RentalTracker $tracker,
    ) {}

    /**
     * Lapse every expired, zero-proof, not-mid-upload booking.
     *
     * @return list<string> the lapsed booking ids
     */
    public function __invoke(?CarbonInterface $now = null, ?int $lookbackHours = null): array
    {
        $at = CarbonImmutable::instance($now ?? now('UTC'));
        $timestamp = $at->toIso8601ZuluString();
        $hours = $lookbackHours !== null && $lookbackHours > 0 ? $lookbackHours : self::DEFAULT_LOOKBACK_HOURS;
        $floor = $at->subHours($hours)->toIso8601ZuluString();

        $rows = $this->client->query(
            'rentalBookings',
            RtdbQuery::orderBy('deadlineAt')->range($floor, $timestamp),
        );

        $lapsed = [];

        foreach ($rows as $id => $row) {
            $booking = RentalBooking::fromNode((string) $id, $row);

            if ($booking === null || ! $this->shouldLapse($booking)) {
                continue;
            }

            $this->client->update("rentalBookings/{$booking->id}", [
                'status' => RentalStatus::Lapsed->value,
                'lapsedAt' => $timestamp,
                // The terminal instant the proof-cleanup sweep keys off.
                'terminalAt' => $timestamp,
            ]);

            $this->tracker->forget($booking->id);

            $lapsed[] = $booking->id;
        }

        return $lapsed;
    }

    /**
     * A zero-proof, not-mid-upload booking, or a flagged hold whose deadline has
     * passed and no human resolved it.
     */
    private function shouldLapse(RentalBooking $booking): bool
    {
        if ($booking->proofInFlight) {
            return false;
        }

        if ($booking->status === RentalStatus::Verifying) {
            return true;
        }

        return $booking->status === RentalStatus::AwaitingPayment
            && $booking->proofReceivedAt === null;
    }
}
