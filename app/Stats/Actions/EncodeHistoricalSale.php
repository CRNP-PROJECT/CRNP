<?php

declare(strict_types=1);

namespace App\Stats\Actions;

use App\Orders\LineItem;
use App\Orders\OrderCode;
use App\Orders\OrderType;
use App\Orders\Payment;
use App\Orders\PaymentMethod;
use App\Rtdb\Contracts\RtdbClient;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Encodes one past sale into the `sales/{id}` ledger — the admin
 * **historical sales encoding** surface (see `config/settings.php`).
 *
 * The row is shaped exactly like a settled walk-in ({@see SettleWalkIn}) so
 * every reader the ledger already has — the sales and payments reports, the
 * dashboard trend, the export — picks it up with no change: it carries
 * `type`, `lines`, `total`, `payments`, and a drawn `code`. Two deliberate
 * choices keep it out of the live surfaces:
 *
 * - it is keyed `hist-{ulid}`, so it never collides with an order id;
 * - it carries **no `handledBy`**, so it stays out of the per-cashier Activity
 *   totals, which are the cashier's accountability figures, not a backfill's.
 *
 * The chosen calendar date becomes `soldAt` at **noon Asia/Manila**, which
 * lands inside both the UTC report range (`D 00:00Z`–`D 23:59:59Z`) and the
 * business-day window every stats reader buckets on, so the sale files under
 * the day the admin picked regardless of the server clock.
 */
final readonly class EncodeHistoricalSale
{
    public function __construct(
        private RtdbClient $client,
        private TopThreeRanking $ranking,
    ) {}

    /**
     * @param  string  $date  the business date, `Y-m-d`
     * @param  list<LineItem>  $lines
     * @return string  the new sale id
     */
    public function __invoke(string $date, PaymentMethod $method, array $lines): string
    {
        $total = LineItem::sum($lines);
        $id = 'hist-' . Str::ulid();

        $this->client->set("sales/{$id}", [
            'code' => OrderCode::draw()->value,
            'type' => OrderType::WalkIn->value,
            'lines' => array_map(static fn(LineItem $line): array => $line->toNode(), $lines),
            'total' => $total,
            'payments' => [(new Payment($method, $total))->toNode()],
            'soldAt' => self::soldAt($date),
        ]);

        // Keep the Top 3 invariant: every `sales/{id}` write recomputes the
        // rolling ranking, so a backfilled recent sale counts and an old one
        // (outside the window) is simply ignored.
        ($this->ranking)();

        return $id;
    }

    /**
     * The chosen business date at noon Asia/Manila, as a UTC instant.
     */
    private static function soldAt(string $date): string
    {
        return CarbonImmutable::parse($date, BusinessTime::timezone())
            ->setTime(12, 0)
            ->toIso8601ZuluString();
    }
}
