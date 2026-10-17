<?php

declare(strict_types=1);

namespace App\Reservations;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;

/**
 * The phone typeahead behind reservation entry (PLAN §4 *Reservations*).
 *
 * Staff type a phone (or the start of one); this reads a **bounded prefix query
 * on the indexed `phone` field** and collapses the matching reservations into
 * one entry per phone, carrying the name, party, and preferred type from that
 * phone's most recent reservation. No parallel customer table exists: the past
 * reservations are the customer record.
 */
final readonly class Typeahead
{
    /** The most matches a single keystroke returns — bounded reads (rule 3). */
    public const LIMIT = 5;

    public function __construct(private RtdbClient $client) {}

    /**
     * The matching customers, most recent first, at most {@see LIMIT}.
     *
     * @return list<CustomerMatch>
     */
    public function search(string $phone): array
    {
        $phone = trim($phone);

        if ($phone === '') {
            return [];
        }

        $rows = $this->client->query(
            'reservations',
            RtdbQuery::orderBy('phone')->startsWith($phone)->limitToFirst(self::LIMIT * 4),
        );

        $latest = [];

        foreach ($rows as $row) {
            $reservation = Reservation::fromNode('match', $row);

            if ($reservation === null || $reservation->status === ReservationStatus::Cancelled) {
                continue;
            }

            $current = $latest[$reservation->phone] ?? null;

            if ($current === null || $this->isNewer($reservation, $current)) {
                $latest[$reservation->phone] = $reservation;
            }
        }

        uasort($latest, fn(Reservation $a, Reservation $b): int => $this->recency($b) <=> $this->recency($a));

        $matches = [];

        foreach (array_slice($latest, 0, self::LIMIT, true) as $reservation) {
            $matches[] = new CustomerMatch(
                name: $reservation->name,
                phone: $reservation->phone,
                party: $reservation->party,
                preferredType: $reservation->preferredType,
            );
        }

        return $matches;
    }

    private function isNewer(Reservation $candidate, Reservation $current): bool
    {
        return $this->recency($candidate) > $this->recency($current);
    }

    /**
     * A comparable recency string: the newest timestamp the row carries, or the
     * empty string when it has none (seeded rows), so a timestamped row wins.
     */
    private function recency(Reservation $reservation): string
    {
        return $reservation->updatedAt ?? $reservation->createdAt ?? '';
    }
}
