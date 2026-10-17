<?php

declare(strict_types=1);

namespace App\Reservations;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;

/**
 * Reads `reservations/{id}` — the single seam for the reservation shape, so no
 * parallel customer table is ever introduced (the phone typeahead reads past
 * reservations, not a separate customer node).
 *
 * Every read is bounded by the `date` index (PLAN §4 *Quota discipline*, rule
 * 3): the conflict check and the occupancy view read one date or a date range,
 * never the whole tree.
 */
final readonly class Reservations
{
    public function __construct(private RtdbClient $client) {}

    /**
     * Every reservation in a date range (inclusive), keyed by id, ordered by
     * date then time.
     *
     * @return array<string, Reservation>
     */
    public function between(string $from, string $to): array
    {
        $rows = $this->client->query('reservations', RtdbQuery::orderBy('date')->range($from, $to));

        return $this->fromRows($rows);
    }

    /**
     * Every reservation on one date, keyed by id.
     *
     * @return array<string, Reservation>
     */
    public function forDate(string $date): array
    {
        $rows = $this->client->query('reservations', RtdbQuery::orderBy('date')->equalTo($date));

        return $this->fromRows($rows);
    }

    public function find(string $id): ?Reservation
    {
        $node = $this->client->get("reservations/{$id}");

        return is_array($node) ? Reservation::fromNode($id, $node) : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, Reservation>
     */
    private function fromRows(array $rows): array
    {
        $reservations = [];

        foreach ($rows as $id => $row) {
            $reservation = Reservation::fromNode($id, $row);

            if ($reservation !== null) {
                $reservations[$id] = $reservation;
            }
        }

        return $reservations;
    }
}
