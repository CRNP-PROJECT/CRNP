<?php

declare(strict_types=1);

namespace App\Proofs;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The **proof cleanup** sweep (PLAN §4 *Data layer* — proof lifecycle).
 *
 * Runs daily at end-of-day (`Asia/Manila`). A GCash screenshot is evidence, not
 * a durable record: the sweep **deletes** the `proofs/{id}` node **7 days** after
 * a terminal order's `settledAt`, 7 days after a rejected proof's `rejectedAt`
 * (watchdog evidence), and 7 days after a **rental booking's** `terminalAt`
 * (`proofs/rental-{id}`; the booking row is left intact). The order row, the
 * rejection record, the rental booking, and the sale are left untouched — only
 * the image bytes go, so the ledger keeps its scalar history and the image
 * storage stays inside the free tier. The same run prunes the `idem/{key}`
 * checkout-replay records once they are older than {@see IDEM_RETAIN_DAYS} —
 * a key only matters within one checkout session, so the node never grows
 * without bound.
 *
 * All reads are bounded by an index **and by an age window** (PLAN §4
 * *Quota discipline*, rule 3): each run touches only the nodes that crossed the
 * threshold since the last run, never every node ever stored. A daily cadence
 * means the window is ample; the documented manual fallback widens it to recover
 * from a scheduler outage.
 */
final readonly class ProofCleanupSweep
{
    /**
     * The age after which a proof image is deleted.
     */
    public const RETAIN_DAYS = 7;

    /**
     * The width of the age window each run scans. Only bounds the read — a daily
     * run crosses every proof exactly once, so a day is ample and the manual
     * fallback widens it after an outage.
     */
    public const DEFAULT_WINDOW_DAYS = 2;

    /**
     * The age after which a checkout idempotency record is pruned. A key only
     * matters within one checkout session (a retry or refresh), so a couple of
     * days is far more than enough and the `idem` tree never grows without
     * bound.
     */
    public const IDEM_RETAIN_DAYS = 2;

    public function __construct(private RtdbClient $client) {}

    /**
     * Delete every proof that crossed the retention window.
     *
     * @param  int|null  $windowDays  widen the range to recover from a
     *                                scheduler outage; defaults to two days
     * @return list<string> the order ids whose proof was deleted
     */
    public function __invoke(?CarbonInterface $now = null, ?int $windowDays = null): array
    {
        $at = CarbonImmutable::instance($now ?? now('UTC'));
        $cutoff = $at->subDays(self::RETAIN_DAYS);
        $window = $windowDays !== null && $windowDays > 0 ? $windowDays : self::DEFAULT_WINDOW_DAYS;
        $floor = $cutoff->subDays($window)->toIso8601ZuluString();
        $ceiling = $cutoff->toIso8601ZuluString();

        $deleted = [];

        // Terminal orders: 7 days after settledAt.
        $settled = $this->client->query(
            'orders',
            RtdbQuery::orderBy('settledAt')->range($floor, $ceiling),
        );

        foreach ($settled as $id => $row) {
            if ($this->deleteProof((string) $id)) {
                $deleted[] = (string) $id;
            }
        }

        // Rejected proofs: 7 days after rejectedAt, even on an order whose
        // settledAt is absent (a rejected order never settles).
        $rejected = $this->client->query(
            'proofs',
            RtdbQuery::orderBy('rejectedAt')->range($floor, $ceiling),
        );

        foreach ($rejected as $id => $row) {
            if ($this->deleteProof((string) $id)) {
                $deleted[] = (string) $id;
            }
        }

        // Rental proofs: 7 days after the booking reached a terminal state
        // (returned / lapsed / cancelled), keyed off its `terminalAt`. The node
        // lives at `proofs/rental-{id}`; the booking record is left intact.
        $rental = $this->client->query(
            'rentalBookings',
            RtdbQuery::orderBy('terminalAt')->range($floor, $ceiling),
        );

        foreach ($rental as $id => $row) {
            $proofId = "rental-{$id}";

            if ($this->deleteProof($proofId)) {
                $deleted[] = $proofId;
            }
        }

        $this->pruneIdempotency($at, $window);

        return array_values(array_unique($deleted));
    }

    /**
     * Delete the proof node for a proof id, reporting whether one existed.
     */
    private function deleteProof(string $proofId): bool
    {
        if ($this->client->get("proofs/{$proofId}") === null) {
            return false;
        }

        $this->client->delete("proofs/{$proofId}");

        return true;
    }

    /**
     * Prune the `idem/{key}` checkout-replay records older than the retention
     * window, so the tree never grows without bound. Bounded by the `updatedAt`
     * index and an age window, the same shape as the proof reads.
     */
    private function pruneIdempotency(CarbonImmutable $at, int $windowDays): void
    {
        $cutoff = $at->subDays(self::IDEM_RETAIN_DAYS);
        $floor = $cutoff->subDays($windowDays)->toIso8601ZuluString();
        $ceiling = $cutoff->toIso8601ZuluString();

        $stale = $this->client->query(
            'idem',
            RtdbQuery::orderBy('updatedAt')->range($floor, $ceiling),
        );

        foreach ($stale as $key => $row) {
            $this->client->delete('idem/' . (string) $key);
        }
    }
}
