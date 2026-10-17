<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Orders\HandledBy;
use App\Rentals\Actions\CancelRentalBooking;
use App\Rentals\Actions\ConfirmRentalProof;
use App\Rentals\Actions\ConfirmRentalReturn;
use App\Rentals\Actions\HandOverRental;
use App\Rentals\RentalBookings;
use App\Rentals\RentalCounterBoard;
use App\Staff\Staff;
use App\Staff\StaffDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The cashier's rental counter (PLAN §4 *Rentals* — counter ops).
 *
 * Four task lists: **flagged** holds (a proof amount/ref mismatch — one-tap
 * Confirm / Cancel), **to hand over** (confirmed), **to return** (on hire), and
 * a quiet **overdue** row for rentals past their return-due date. **Hand over**
 * lowers stock and stamps `handledBy`; **Confirm return** raises it by the due
 * date. All are status-guarded, so a stale or duplicate tap is a friendly
 * no-op. Overdue carries no penalty in v1. All routes sit behind the
 * `staff.role:cashier` guard.
 */
final class RentalController extends Controller
{
    public function __construct(
        private readonly RentalCounterBoard $board,
        private readonly RentalBookings $bookings,
        private readonly StaffDirectory $directory,
    ) {}

    public function index(): View
    {
        return view('cashier.rentals.index', [
            'counter' => $this->board->snapshot(),
            'cashier' => $this->directory->forUser(auth()->user()),
        ]);
    }

    /**
     * Hand over a confirmed booking: stock falls, `handledBy` is stamped. A
     * stale or duplicate tap is a friendly no-op.
     */
    public function handOver(string $booking, HandOverRental $handOver): RedirectResponse
    {
        if ($this->bookings->find($booking) === null) {
            throw new NotFoundHttpException("No rental booking with id [{$booking}].");
        }

        $handOver($booking, $this->cashier());

        return redirect()
            ->route('cashier.rentals.index')
            ->with('status', 'Rental handed over.');
    }

    /**
     * Confirm a return: stock rises by the booking's quantity. A stale or
     * duplicate tap is a friendly no-op.
     */
    public function confirmReturn(string $booking, ConfirmRentalReturn $confirm): RedirectResponse
    {
        if ($this->bookings->find($booking) === null) {
            throw new NotFoundHttpException("No rental booking with id [{$booking}].");
        }

        $confirm($booking, $this->cashier());

        return redirect()
            ->route('cashier.rentals.index')
            ->with('status', 'Rental return confirmed.');
    }

    /**
     * Confirm a flagged rental proof: the booking becomes `confirmed` (units
     * reserved, stock untouched). A stale or duplicate tap is a friendly no-op.
     */
    public function confirmProof(string $booking, ConfirmRentalProof $confirm): RedirectResponse
    {
        if ($this->bookings->find($booking) === null) {
            throw new NotFoundHttpException("No rental booking with id [{$booking}].");
        }

        $confirm($booking, $this->cashier());

        return redirect()
            ->route('cashier.rentals.index')
            ->with('status', 'Rental payment confirmed.');
    }

    /**
     * Cancel a flagged rental booking: the booking ends `cancelled` with stock
     * untouched. A stale or duplicate tap is a friendly no-op.
     */
    public function cancel(string $booking, CancelRentalBooking $cancel): RedirectResponse
    {
        if ($this->bookings->find($booking) === null) {
            throw new NotFoundHttpException("No rental booking with id [{$booking}].");
        }

        $cancel($booking, $this->cashier());

        return redirect()
            ->route('cashier.rentals.index')
            ->with('status', 'Rental booking cancelled.');
    }

    /**
     * The cashier on duty, stamped on every handover / return.
     */
    private function cashier(): HandledBy
    {
        $staff = $this->directory->forUser(auth()->user());

        if (! $staff instanceof Staff) {
            abort(403, 'This area is for staff accounts only.');
        }

        return HandledBy::fromStaff($staff);
    }
}
