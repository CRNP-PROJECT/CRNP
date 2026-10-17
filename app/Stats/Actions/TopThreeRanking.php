<?php

declare(strict_types=1);

namespace App\Stats\Actions;

use App\Cache\CatalogCache;
use App\Orders\LineItem;
use App\Orders\OrderType;
use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use Carbon\CarbonImmutable;

/**
 * Recomputes the **Top 3** ranking into `stats/top3` (PLAN §4 *Customer site* —
 * *Item tags*).
 *
 * It runs eagerly on every `sales/{id}` write — Mark served, the close sweep's
 * synthesized Unclaimed sale, and a settled walk-in — over the rolling **7-day
 * FIFO window** read from the `sales/.indexOn soldAt` query. Raw sales rows are
 * never deleted: the window bounds what is *ranked*, not what is *stored*.
 *
 * The ranking set is the **revenue set**: a `sales/{id}` row is only ever
 * written for a paid order, so reading sales is reading revenue. A non-order
 * sale type (a rental booking, #34) is excluded defensively. Products are
 * ranked by units sold, ties broken by revenue then name; the chip appears once
 * **three distinct products** have a sale and always shows the current top ≤ 3,
 * and a window below the gate stores no rankings so the product card shows no
 * tag.
 *
 * The result is cached at `stats/top3`, its own small entry (PLAN §4 *Quota
 * discipline*, rule 2). Recomputing evicts that entry so the next menu read sees
 * the new ranking, and never the catalog entry — a sale must not bust the
 * base64 menu.
 */
final readonly class TopThreeRanking
{
    /** The rolling window the ranking covers. */
    public const WINDOW_DAYS = 7;

    /** The gate: below this many distinct products the chip stays off. */
    public const MIN_DISTINCT_PRODUCTS = 3;

    /** The chip shows at most this many ranks. */
    public const MAX_RANKS = 3;

    public function __construct(private RtdbClient $client) {}

    public function __invoke(): void
    {
        $this->client->set('stats/top3', ['rankings' => $this->rank($this->windowSales())]);

        // Its own coalesced entry (rule 2): evict it so the next product-card
        // read sees the new ranking — and never the catalog, so a sale never
        // busts the base64 menu.
        $this->client->forget(CatalogCache::TOP3_KEY);
    }

    /**
     * The ranked rows to store, or an empty list when the gate is not met.
     *
     * @param  list<array<string, mixed>>  $sales
     * @return list<array{productId: string, name: string, units: int, revenue: float}>
     */
    private function rank(array $sales): array
    {
        /** @var array<string, array{name: string, units: int, revenue: float}> $tally */
        $tally = [];

        foreach ($sales as $sale) {
            if (! $this->isOrderSale($sale)) {
                continue;
            }

            foreach ($this->lines($sale) as $line) {
                $row = $tally[$line->productId] ?? ['name' => $line->name, 'units' => 0, 'revenue' => 0.0];

                $tally[$line->productId] = [
                    'name' => $row['name'],
                    'units' => $row['units'] + $line->quantity,
                    'revenue' => round($row['revenue'] + $line->lineTotal(), 2),
                ];
            }
        }

        if (count($tally) < self::MIN_DISTINCT_PRODUCTS) {
            return [];
        }

        uasort($tally, static function (array $a, array $b): int {
            return $b['units'] <=> $a['units']
                ?: $b['revenue'] <=> $a['revenue']
                ?: strcmp($a['name'], $b['name']);
        });

        $ranked = [];

        foreach (array_slice($tally, 0, self::MAX_RANKS, true) as $productId => $row) {
            $ranked[] = [
                'productId' => $productId,
                'name' => $row['name'],
                'units' => $row['units'],
                'revenue' => $row['revenue'],
            ];
        }

        return $ranked;
    }

    /**
     * The `sales/{id}` rows inside the rolling window, from the `soldAt` index.
     *
     * @return list<array<string, mixed>>
     */
    private function windowSales(): array
    {
        $now = CarbonImmutable::instance(now('UTC'));

        $rows = $this->client->query(
            'sales',
            RtdbQuery::orderBy('soldAt')->range(
                $now->subDays(self::WINDOW_DAYS)->toIso8601ZuluString(),
                $now->toIso8601ZuluString(),
            ),
        );

        return array_values($rows);
    }

    /**
     * Whether a sale row belongs to the revenue set — an order sale, never a
     * rental booking.
     *
     * @param  array<string, mixed>  $sale
     */
    private function isOrderSale(array $sale): bool
    {
        $type = $sale['type'] ?? null;

        return is_string($type) && OrderType::tryFrom($type) !== null;
    }

    /**
     * @param  array<string, mixed>  $sale
     * @return list<LineItem>
     */
    private function lines(array $sale): array
    {
        $node = $sale['lines'] ?? null;

        if (! is_array($node)) {
            return [];
        }

        $lines = [];

        foreach ($node as $row) {
            if (! is_array($row)) {
                continue;
            }

            $line = LineItem::fromNode($row);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
