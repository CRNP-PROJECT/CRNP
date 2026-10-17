<?php

declare(strict_types=1);

namespace App\Reservations;

use App\Settings\ReservationArea;
use App\Settings\Settings;
use App\Settings\SettingsRepository;
use App\Support\BusinessTime;
use Carbon\CarbonInterface;

/**
 * Computes the public **occupancy view**: seats taken vs capacity per day and
 * time slot for the week ahead (PLAN §4 *Reservations*).
 *
 * **Confirmed bookings only** — pending and cancelled never count, because a
 * pending reservation is not yet a commitment and a cancelled one has freed its
 * seats. The result is **aggregate only**: per area, a taken count and the
 * configured capacity, never a name or a phone. Nothing here books.
 *
 * The read is bounded by the `date` index to the seven-day window, so a page
 * view never scans the tree; the computed result is what the availability cache
 * coalesces (rule 2).
 */
final readonly class Occupancy
{
    /** The length of the public window, in days. */
    public const WINDOW_DAYS = 7;

    public function __construct(
        private Reservations $reservations,
        private SettingsRepository $settings,
    ) {}

    /**
     * The occupancy for the seven days starting at $from's date, in business
     * time.
     *
     * @return list<OccupancyDay>
     */
    public function week(CarbonInterface $from): array
    {
        $start = $from->copy()->setTimezone(BusinessTime::timezone())->startOfDay();
        $dates = [];

        for ($offset = 0; $offset < self::WINDOW_DAYS; $offset++) {
            $dates[] = $start->copy()->addDays($offset)->toDateString();
        }

        $confirmed = array_filter(
            $this->reservations->between($dates[0], $dates[self::WINDOW_DAYS - 1]),
            static fn(Reservation $reservation): bool => $reservation->status === ReservationStatus::Confirmed,
        );

        $settings = $this->settings->current();
        $days = [];

        foreach ($dates as $date) {
            $days[] = new OccupancyDay($date, $this->slotsFor($date, $confirmed, $settings));
        }

        return $days;
    }

    /**
     * @param  array<string, Reservation>  $confirmed
     * @return list<OccupancySlot>
     */
    private function slotsFor(string $date, array $confirmed, Settings $settings): array
    {
        /** @var array<string, array<string, int>> $taken slot key => area value => seats */
        $taken = [];

        foreach ($confirmed as $reservation) {
            if ($reservation->date !== $date) {
                continue;
            }

            $slot = $reservation->slot()->key();
            $area = $reservation->type->value;

            $taken[$slot][$area] = ($taken[$slot][$area] ?? 0) + $reservation->party;
        }

        ksort($taken);

        $slots = [];

        foreach ($taken as $slotKey => $areas) {
            $counts = [];

            foreach (ReservationArea::cases() as $area) {
                $counts[] = new OccupancyCount(
                    area: $area,
                    taken: $areas[$area->value] ?? 0,
                    capacity: $settings->capacityFor($area),
                );
            }

            $slots[] = new OccupancySlot(TimeSlot::fromClock($slotKey), $counts);
        }

        return $slots;
    }
}
