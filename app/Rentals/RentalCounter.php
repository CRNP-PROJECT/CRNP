<?php

declare(strict_types=1);

namespace App\Rentals;

/**
 * The counter's rental task lists (PLAN §4 *Rentals* — counter ops).
 *
 * - **to hand over** — confirmed bookings awaiting pickup; the tap lowers stock.
 * - **to return** — handed-over bookings still on hire; the tap raises stock.
 * - **overdue** — handed-over bookings past their return-due date, a quiet row
 *   with no auto-penalty in v1.
 * - **flagged** — bookings whose proof amount/ref mismatched, parked for a
 *   one-tap **Confirm** (→ confirmed) or **Cancel** (→ cancelled) so the
 *   exception reaches a human rather than sitting stuck.
 *
 * Each list is built from a status-bounded query, so the counter never scans
 * the booking tree.
 */
final readonly class RentalCounter
{
    /**
     * @param  list<RentalBooking>  $toHandOver
     * @param  list<RentalBooking>  $toReturn
     * @param  list<RentalBooking>  $overdue
     * @param  list<RentalBooking>  $flagged
     */
    public function __construct(
        public array $toHandOver,
        public array $toReturn,
        public array $overdue,
        public array $flagged = [],
    ) {}
}
