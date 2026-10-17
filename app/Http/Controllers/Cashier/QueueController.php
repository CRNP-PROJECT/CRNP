<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Cache\CashierQueueCache;
use App\Http\Controllers\Controller;
use App\Orders\Actions\ApproveOrder;
use App\Orders\Actions\CollectLate;
use App\Orders\Actions\MarkServed;
use App\Orders\Actions\RejectOrder;
use App\Orders\Actions\Restore;
use App\Orders\Actions\VoidOrder;
use App\Orders\CashierQueue;
use App\Orders\HandledBy;
use App\Orders\Orders;
use App\Orders\QueueSnapshot;
use App\Staff\Staff;
use App\Staff\StaffDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The cashier's **online pickup queue** and its taps (PLAN §4 *Cashier POS*).
 *
 * The screen shows the queue sections — flagged holds (one-tap Approve /
 * Reject), ready-to-pay, recently-verified (spot-check, Reject reachable until
 * Mark served), awaiting-proof, and the Rejected list (Approve or Void, never
 * Restore) — plus the **ready — awaiting handover** row for tickets the server
 * cleared off the board, and the **Dismissed** (Restore / Void) and
 * **Unclaimed** (Collect late) lists (#29). It names the cashier on duty in the
 * register header. Mark served is the one happy-path tap: it flips the order to
 * `served` (the tracker reads READY) and writes the 1:1 `sales/{id}` row with
 * `handledBy`; a stale or duplicate tap is a friendly no-op. Reject writes the
 * rejection record and books `refundOwed`; Approve is the undo; Void ends a
 * Rejected or Dismissed order. The read is served from the coalesced
 * {@see CashierQueueCache} entry, so N registers cost one RTDB read per poll
 * cadence.
 *
 * The controller validates, authorizes, delegates, and responds — the queue
 * computation lives in {@see CashierQueue} and the writes in the action classes.
 */
final class QueueController extends Controller
{
    public function __construct(
        private readonly CashierQueue $queue,
        private readonly CashierQueueCache $cache,
        private readonly Orders $orders,
        private readonly StaffDirectory $directory,
    ) {}

    /**
     * The queue screen, served from the coalesced entry.
     */
    public function index(): View
    {
        return view('cashier.queue', [
            'snapshot' => $this->snapshot(),
            'pollSeconds' => CashierQueue::POLL_SECONDS,
            'cashier' => $this->directory->forUser(auth()->user()),
        ]);
    }

    /**
     * The live queue state the screen polls — never older than the poll cadence,
     * and never carrying proof image bytes.
     */
    public function state(): JsonResponse
    {
        return response()->json($this->snapshot()->toArray());
    }

    /**
     * Mark an online pickup order served: the one happy-path tap. A stale or
     * duplicate tap is a friendly no-op, so it always redirects back to the
     * queue.
     */
    public function serve(string $order, MarkServed $markServed): RedirectResponse
    {
        if ($this->orders->find($order) === null) {
            throw new NotFoundHttpException("No order with id [{$order}].");
        }

        $markServed($order, $this->cashier());

        return redirect()->route('cashier.queue');
    }

    /**
     * Reject a flagged hold or a spot-checked verified order: write the
     * rejection record, keep the image, and book `refundOwed`. A stale or
     * duplicate tap is a friendly no-op.
     */
    public function reject(string $order, RejectOrder $reject): RedirectResponse
    {
        if ($this->orders->find($order) === null) {
            throw new NotFoundHttpException("No order with id [{$order}].");
        }

        $reject($order, $this->cashier());

        return redirect()->route('cashier.queue');
    }

    /**
     * Approve a rejected proof — the undo. The ticket rejoins the board by age
     * with the timer unchanged. Only a rejected order is touched; any other
     * status is a friendly no-op.
     */
    public function approve(string $order, ApproveOrder $approve): RedirectResponse
    {
        if ($this->orders->find($order) === null) {
            throw new NotFoundHttpException("No order with id [{$order}].");
        }

        $approve($order);

        return redirect()->route('cashier.queue');
    }

    /**
     * Restore a Dismissed order: re-arm a fresh 15-minute window, flip the
     * tracker back, and write an audit entry. A stale or duplicate tap is a
     * friendly no-op.
     */
    public function restore(string $order, Restore $restore): RedirectResponse
    {
        if ($this->orders->find($order) === null) {
            throw new NotFoundHttpException("No order with id [{$order}].");
        }

        $restore($order, $this->cashier());

        return redirect()->route('cashier.queue');
    }

    /**
     * Void a Rejected or Dismissed order: end it, never clearing `refundOwed`.
     * A stale or duplicate tap is a friendly no-op.
     */
    public function void(string $order, VoidOrder $void): RedirectResponse
    {
        if ($this->orders->find($order) === null) {
            throw new NotFoundHttpException("No order with id [{$order}].");
        }

        $void($order, $this->cashier());

        return redirect()->route('cashier.queue');
    }

    /**
     * Collect an Unclaimed order late: flip it to served, stamp `handledBy`, and
     * write no second sale. A stale or duplicate tap is a friendly no-op.
     */
    public function collectLate(string $order, CollectLate $collectLate): RedirectResponse
    {
        if ($this->orders->find($order) === null) {
            throw new NotFoundHttpException("No order with id [{$order}].");
        }

        $collectLate($order, $this->cashier());

        return redirect()->route('cashier.queue');
    }

    private function snapshot(): QueueSnapshot
    {
        /** @var QueueSnapshot $snapshot */
        $snapshot = $this->cache->remember(fn(): QueueSnapshot => $this->queue->snapshot());

        return $snapshot;
    }

    /**
     * The cashier on duty, stamped on every money write.
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
