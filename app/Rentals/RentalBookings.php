<?php

declare(strict_types=1);

namespace App\Rentals;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;

/**
 * Reads `rentalBookings/{id}` — the single seam for the booking shape.
 *
 * Every read is bounded by the `dateFrom` index (PLAN §4 *Quota discipline*,
 * rule 3): a range of interest is turned into a bounded `dateFrom` window, and
 * a row is kept only when its own `dateTo` reaches into the range. Because a
 * booking can never span more than {@see RentalPeriod::maxDays()}, a booking that
 * overlaps a window must start no earlier than the window's start minus that
 * maximum — so the read never scans the whole tree.
 */
final readonly class RentalBookings
{
    public function __construct(private RtdbClient $client) {}

    public function find(string $id): ?RentalBooking
    {
        $node = $this->client->get("rentalBookings/{$id}");

        return is_array($node) ? RentalBooking::fromNode($id, $node) : null;
    }

    /**
     * Every booking whose range overlaps [$from, $to], keyed by id.
     *
     * @return array<string, RentalBooking>
     */
    public function overlapping(RentalPeriod $period): array
    {
        $floor = $period->from->subDays(RentalPeriod::maxDays())->toDateString();

        $rows = $this->client->query(
            'rentalBookings',
            RtdbQuery::orderBy('dateFrom')->range($floor, $period->toString()),
        );

        $bookings = [];

        foreach ($rows as $id => $row) {
            $booking = RentalBooking::fromNode((string) $id, $row);

            if ($booking !== null && $booking->period->overlaps($period)) {
                $bookings[(string) $id] = $booking;
            }
        }

        return $bookings;
    }

    /**
     * Every booking in one status, keyed by id — for the counter's handover /
     * return / overdue lists. The read is a status-bounded query, not a scan.
     *
     * @return array<string, RentalBooking>
     */
    public function byStatus(RentalStatus $status): array
    {
        $rows = $this->client->query(
            'rentalBookings',
            RtdbQuery::orderBy('status')->equalTo($status->value),
        );

        $bookings = [];

        foreach ($rows as $id => $row) {
            $booking = RentalBooking::fromNode((string) $id, $row);

            if ($booking !== null) {
                $bookings[(string) $id] = $booking;
            }
        }

        return $bookings;
    }

    /**
     * Every booking the customer holds, newest first — the account's own list.
     *
     * @return array<string, RentalBooking>
     */
    public function forUser(string $uid): array
    {
        $rows = $this->client->query(
            'rentalBookings',
            RtdbQuery::orderBy('uid')->equalTo($uid),
        );

        $bookings = [];

        foreach ($rows as $id => $row) {
            $booking = RentalBooking::fromNode((string) $id, $row);

            if ($booking !== null) {
                $bookings[(string) $id] = $booking;
            }
        }

        return $bookings;
    }
}
