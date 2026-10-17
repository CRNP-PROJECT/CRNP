<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Auth\RtdbUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SubmitProofRequest;
use App\Orders\Order;
use App\Orders\Orders;
use App\Proofs\Actions\StartProofUpload;
use App\Proofs\Actions\SubmitProof;
use App\Proofs\ProofNotAccepted;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The proof-of-payment surface the tracker drives (PLAN §4 *Customer site* —
 * proof of payment).
 *
 * `start` records the in-flight marker the instant the picker selection begins
 * uploading, so a request sent with no terminal response holds the order across
 * 15:00 (#25). `store` accepts the screenshot and the two fields, delegates the
 * re-encode and the auto-verify/flagged verdict to {@see SubmitProof}, and
 * responds — the client then re-polls the tracker for the verdict.
 *
 * Both actions are owner-gated: only the order's `customerUid` may attach a
 * proof, so a leaked order id is not a proof-write capability.
 */
final class ProofController extends Controller
{
    public function __construct(private readonly Orders $orders) {}

    /**
     * Mark an upload as in flight — persisted when the upload begins.
     */
    public function start(Request $request, string $order, StartProofUpload $start): Response
    {
        $this->owned($request, $order);

        try {
            $start($order);
        } catch (ProofNotAccepted $exception) {
            abort(403, $exception->getMessage());
        }

        return response()->noContent();
    }

    /**
     * Accept the screenshot + amount + ref#; auto-verify or flag the hold.
     */
    public function store(SubmitProofRequest $request, string $order, SubmitProof $submit): Response
    {
        $this->owned($request, $order);

        $image = $request->imageBytes();

        if ($image === null) {
            abort(422, 'The uploaded screenshot could not be read.');
        }

        try {
            $submit($order, $request->amount(), $request->reference(), $image);
        } catch (ProofNotAccepted $exception) {
            abort(403, $exception->getMessage());
        }

        return response()->noContent();
    }

    /**
     * The order, only when the signed-in account owns it.
     */
    private function owned(Request $request, string $orderId): Order
    {
        $user = $request->user();

        abort_unless($user instanceof RtdbUser, 403);

        $order = $this->orders->find($orderId);

        if ($order === null) {
            throw new NotFoundHttpException("No order with id [{$orderId}].");
        }

        abort_unless($order->customerUid === $user->sub, 403);

        return $order;
    }
}
