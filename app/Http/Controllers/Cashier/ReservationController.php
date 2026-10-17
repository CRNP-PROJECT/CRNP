<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\ReservationDetailsRequest;
use App\Http\Requests\Cashier\StoreReservationRequest;
use App\Reservations\Actions\CancelReservation;
use App\Reservations\Actions\ConfirmReservation;
use App\Reservations\Actions\CreateReservation;
use App\Reservations\CustomerMatch;
use App\Reservations\Occupancy;
use App\Reservations\ReservationBlocked;
use App\Reservations\ReservationPrefill;
use App\Reservations\Reservations;
use App\Reservations\Typeahead;
use App\Settings\ReservationArea;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reservation entry and the calendar/list view (PLAN §4 *Reservations*).
 *
 * Entry is lookup-first: the cashier types the phone, a typeahead prefills a
 * returning customer's name, party, and preferred type, and the cashier adjusts
 * the date/time and confirms. A brand-new customer is captured in two guided
 * steps (≤3 then ≤2 inputs). The conflict and duplicate check runs server-side
 * before the write and again on confirm, so a double-booking is blocked with a
 * reason. All routes sit behind the `staff.role:cashier` guard.
 */
final class ReservationController extends Controller
{
    public function __construct(
        private readonly Reservations $reservations,
        private readonly Typeahead $typeahead,
    ) {}

    /**
     * The calendar/list view: reservations grouped by date, with confirm/cancel
     * statuses and the two status actions. The default window is the week ahead;
     * a `?date=YYYY-MM-DD` narrows it to one day.
     */
    public function index(Request $request): View
    {
        $from = $this->requestedDate($request);
        $to = CarbonImmutable::createFromFormat('Y-m-d', $from)
            ?->addDays(Occupancy::WINDOW_DAYS - 1)
            ->toDateString() ?? $from;

        $grouped = [];

        foreach ($this->reservations->between($from, $to) as $reservation) {
            $grouped[$reservation->date][$reservation->id] = $reservation;
        }

        ksort($grouped);

        return view('cashier.reservations.index', [
            'from' => $from,
            'to' => $to,
            'grouped' => $grouped,
        ]);
    }

    /**
     * Step 1 for a new customer: name · phone · type chips (≤3 inputs).
     */
    public function create(): View
    {
        return view('cashier.reservations.create', [
            'areas' => ReservationArea::cases(),
        ]);
    }

    /**
     * The phone typeahead: a bounded prefix query returning past reservations,
     * aggregate-free but prefill-shaped (name, party, preferred type).
     */
    public function lookup(Request $request): JsonResponse
    {
        $phone = $request->query('phone');
        $phone = is_string($phone) ? $phone : '';

        $customers = array_map(
            static fn(CustomerMatch $match): array => [
                'name' => $match->name,
                'phone' => $match->phone,
                'party' => $match->party,
                'preferredType' => $match->preferredType->value,
            ],
            $this->typeahead->search($phone),
        );

        return response()->json(['customers' => $customers]);
    }

    /**
     * Step 2: the server prefills a returning customer and renders the
     * date/time + party stepper form (≤2 inputs).
     */
    public function details(ReservationDetailsRequest $request): View
    {
        $prefill = ReservationPrefill::forEntry(
            $this->typeahead,
            $request->phone(),
            $request->name(),
            $request->area(),
        );

        return view('cashier.reservations.details', [
            'prefill' => $prefill,
            'today' => now(BusinessTime::timezone())->toDateString(),
        ]);
    }

    /**
     * Store the reservation after the server-run conflict and duplicate check.
     */
    public function store(StoreReservationRequest $request, CreateReservation $create): RedirectResponse
    {
        try {
            $create($request->draft());
        } catch (ReservationBlocked $blocked) {
            return back()
                ->withInput()
                ->withErrors(['reservation' => $blocked->getMessage()]);
        }

        return redirect()
            ->route('cashier.reservations.index')
            ->with('status', 'Reservation entered.');
    }

    public function confirm(string $reservation, ConfirmReservation $confirm): RedirectResponse
    {
        try {
            $confirm($reservation);
        } catch (ReservationBlocked $blocked) {
            return back()->withErrors(['reservation' => $blocked->getMessage()]);
        }

        return redirect()
            ->route('cashier.reservations.index')
            ->with('status', 'Reservation confirmed.');
    }

    public function cancel(string $reservation, CancelReservation $cancel): RedirectResponse
    {
        $cancel($reservation);

        return redirect()
            ->route('cashier.reservations.index')
            ->with('status', 'Reservation cancelled.');
    }

    private function requestedDate(Request $request): string
    {
        $date = $request->query('date');

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        return now(BusinessTime::timezone())->toDateString();
    }
}
