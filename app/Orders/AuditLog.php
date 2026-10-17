<?php

declare(strict_types=1);

namespace App\Orders;

use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Support\Str;

/**
 * The **audit** log at `audit/{id}` (PLAN §4 *Cashier POS* — *Retired orders*).
 *
 * A Restore writes an entry carrying the order id, who did it (uid + name), and
 * when — so a re-dismiss reads as a second real lapse with an explicit human act
 * behind it, not a bug. The `audit` tree is indexed on `orderId`, so admin
 * Activity can read a single order's history back without a whole-tree scan.
 *
 * Entries are append-only: each is its own keyed node, so unlimited restores
 * each leave their own record. The shape is `{orderId, code, action, who, when}`
 * — the order id / code / who / when the admin Activity feed (#32) reads back.
 */
final readonly class AuditLog
{
    public function __construct(private RtdbClient $client) {}

    /**
     * Append an entry for an order action.
     */
    public function record(string $orderId, string $action, HandledBy $handledBy, ?string $code = null): void
    {
        $when = now('UTC')->toIso8601ZuluString();

        $node = [
            'orderId' => $orderId,
            'action' => $action,
            'who' => $handledBy->toNode(),
            'when' => $when,
        ];

        // The order code rides along so the Activity feed can show it without a
        // per-entry order read.
        if ($code !== null && $code !== '') {
            $node['code'] = $code;
        }

        $this->client->set('audit/' . (string) Str::ulid(), $node);
    }
}
