<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Auth\RtdbUser;
use App\Customer\OrderingGate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\BookRentalRequest;
use App\Http\Requests\Customer\SubmitProofRequest;
use App\Rentals\Actions\BookRental;
use App\Rentals\Actions\StartRentalProofUpload;
use App\Rentals\Actions\SubmitRentalProof;
use App\Rentals\RentalBlocked;
use App\Rentals\RentalBookings;
use App\Rentals\RentalCatalog;
use App\Rentals\RentalProofNotAccepted;
use App\Rentals\RentalTracker;
use App\Rentals\RentalTrackerState;
use App\Settings\SettingsRepository;
use App\Settings\StoreStatus;
use App\Support\BusinessTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public rental booking surface (PLAN §4 *Rentals*).
 *
 * The item page shows the price/day and the computed per-date availability
 * behind the same open/closed badge as place-order, plus a date-range + quantity
 * form (≤3 inputs). Booking is sign-in-gated, computes the total server-side,
 * and opens the **same GCash gate as orders** — the QR is sent and the 15-minute
 * window starts. The account-bound tracker reopens with zero typing and accepts
 * a proof with an amount/ref flag and auto-verify.
 *
 * The booking is its own stream: it never writes to the kitchen board or
 * `sales/{id}`, so the catalog and Top 3 never see it.
 */
final class RentalBookingController extends Controller
{
    public function __construct(
        private readonly RentalCatalog $catalog,
        private readonly RentalBookings $bookings,
        private readonly RentalTracker $tracker,
        private readonly SettingsRepository $settings,
        private readonly StoreStatus $status,
    ) {}

    /**
     * The item page: price/day, the per-date availability, and the booking form.
     */
    public function show(Request $request, string $item): View|RedirectResponse
    {
        $entry = $this->catalog->entry($item, now());

        if ($entry === null) {
            throw new NotFoundHttpException("No rental item with id [{$item}].");
        }

        $settings = $this->settings->current();

        return view('rentals.show', [
            'entry' => $entry,
            'gate' => OrderingGate::for($settings, $this->status, now()),
            'today' => now(BusinessTime::timezone())->toDateString(),
        ]);
    }

    /**
     * Place a rental booking: compute the total, reserve it (a conflict re-check
     * runs server-side), and open the 15-minute GCash window. Sign-in-gated.
     */
    public function store(
        BookRentalRequest $request,
        string $item,
        BookRental $book,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof RtdbUser) {
            return redirect()->route('login');
        }

        $settings = $this->settings->current();

        // The gate is re-checked at POST: booking follows the diner's hours and
        // needs the payment block, exactly like place-order.
        abort_unless(
            OrderingGate::for($settings, $this->status, now())->canOrder,
            403,
        );

        try {
            $booking = $book($user, $item, $request->period(), $request->quantity());
        } catch (RentalBlocked $blocked) {
            return back()->withInput()->withErrors(['rental' => $blocked->getMessage()]);
        }

        return redirect()->route('rentals.track', ['booking' => $booking->id]);
    }

    /**
     * The account's bookings, newest first.
     */
    public function mine(Request $request): View
    {
        $user = $request->user();

        abort_unless($user instanceof RtdbUser, 403);

        return view('rentals.mine', [
            'bookings' => $this->bookings->forUser($user->sub),
        ]);
    }

    /**
     * The booking tracker: a live status screen bound to the account, with the
     * GCash frame and the proof surface, exactly like an order tracker.
     */
    public function track(Request $request, string $booking): View
    {
        $state = $this->ownedState($request, $booking);
        $settings = $this->settings->current();

        return view('rentals.tracker', [
            'state' => $state,
            'pollSeconds' => RentalTracker::POLL_SECONDS,
            'gcashNumber' => $settings->gcashNumber,
            'gcashQr' => $settings->gcashQr,
        ]);
    }

    /**
     * The live tracker state the screen polls — never older than the cadence.
     */
    public function state(Request $request, string $booking): JsonResponse
    {
        return response()->json($this->ownedState($request, $booking)->toArray());
    }

    /**
     * Mark a proof upload in flight so it holds the booking across the deadline.
     */
    public function proofStart(Request $request, string $booking, StartRentalProofUpload $start): Response
    {
        $this->ownedState($request, $booking);

        try {
            $start($booking);
        } catch (RentalProofNotAccepted $exception) {
            abort(403, $exception->getMessage());
        }

        return response()->noContent();
    }

    /**
     * Accept a GCash proof for the booking: re-encode, auto-verify on a match,
     * or flag a hold on an amount/ref mismatch — the same gate as orders.
     */
    public function proof(Request $request, string $booking, SubmitProofRequest $proofRequest, SubmitRentalProof $submit): Response
    {
        $this->ownedState($request, $booking);

        $image = $proofRequest->imageBytes();

        if ($image === null) {
            abort(422, 'The uploaded screenshot could not be read.');
        }

        try {
            $submit($booking, $proofRequest->amount(), $proofRequest->reference(), $image);
        } catch (RentalProofNotAccepted $exception) {
            abort(403, $exception->getMessage());
        }

        return response()->noContent();
    }

    private function ownedState(Request $request, string $bookingId): RentalTrackerState
    {
        $user = $request->user();

        abort_unless($user instanceof RtdbUser, 403);

        $state = $this->tracker->state($bookingId);

        if ($state === null) {
            throw new NotFoundHttpException("No rental booking with id [{$bookingId}].");
        }

        abort_unless($state->uid === $user->sub, 403);

        return $state;
    }
}
