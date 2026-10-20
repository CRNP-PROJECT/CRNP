<?php

declare(strict_types=1);

namespace App\Stats;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;

/**
 * Reads the `rentalBookings` tree for the dashboard's monthly booking KPIs.
 *
 * A rental booking is its own revenue stream (CONTEXT — *Rental booking*): it
 * never reaches `sales/{id}`, so the order {@see SalesLedger} cannot see it and
 * the bookings need their own bounded read. The query is keyed by the indexed
 * `dateFrom` field — the same bound the admin rental-booking report uses — so a
 * KPI row costs one month of rows, never the whole tree.
 */
final readonly class BookingLedger
{
    public function __construct(private RtdbClient $client) {}

    /**
     * Bookings whose rental period starts in the `Y-m-d` date range [$fromDate,
     * $toDate] inclusive, keyed by booking id, every status included — the
     * caller decides what counts as paid.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fromDateRange(string $fromDate, string $toDate): array
    {
        return $this->client->query('rentalBookings', RtdbQuery::orderBy('dateFrom')->range($fromDate, $toDate));
    }
}
