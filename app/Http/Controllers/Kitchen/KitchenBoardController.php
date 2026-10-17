<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kitchen;

use App\Cache\KitchenBoardCache;
use App\Http\Controllers\Controller;
use App\Kitchen\KitchenBoard;
use App\Kitchen\KitchenSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The kitchen **read-only board** (PLAN §4 *Kitchen board*).
 *
 * The route is gated by the shared secret from settings (#20) — the
 * {@see \App\Http\Middleware\EnsureKitchenSecret} middleware — so the board
 * needs no login and the cook never touches it. The screen renders NOW
 * (oldest-first, age timers, NEW badge, all-day counts) and LATER (dimmed
 * scheduled pickups with countdowns), and polls a small JSON state endpoint
 * every {@see KitchenBoard::POLL_SECONDS} seconds.
 *
 * Both the page and the state endpoint serve from the same coalesced
 * {@see KitchenBoardCache} entry, so N kiosks cost one RTDB read per interval
 * (PLAN §4 *Quota discipline*, rule 1). The controller validates, delegates,
 * and responds — the lane and timer logic lives in {@see KitchenBoard}.
 */
final class KitchenBoardController extends Controller
{
    public function __construct(
        private readonly KitchenBoard $board,
        private readonly KitchenBoardCache $cache,
    ) {}

    /**
     * The board screen. The poll URL preserves how the secret arrived — a path
     * segment or a query string — so a kiosk keeps polling the same gate.
     */
    public function board(Request $request): View
    {
        return view('kitchen.board', [
            'snapshot' => $this->snapshot(),
            'pollSeconds' => KitchenBoard::POLL_SECONDS,
            'staleSeconds' => self::STALE_SECONDS,
            'pollUrl' => $this->stateUrl($request),
        ]);
    }

    /**
     * The live board state the kiosk polls — never older than the poll cadence,
     * and never carrying proof image bytes.
     */
    public function state(): JsonResponse
    {
        return response()->json($this->snapshot()->toArray());
    }

    /**
     * The heartbeat threshold: past this many seconds without a fresh poll the
     * board shows "Reconnecting…" and auto-reloads with backoff.
     */
    private const STALE_SECONDS = 15;

    private function snapshot(): KitchenSnapshot
    {
        /** @var KitchenSnapshot $snapshot */
        $snapshot = $this->cache->remember(fn(): KitchenSnapshot => $this->board->snapshot());

        return $snapshot;
    }

    /**
     * The state endpoint for the gate the request came through.
     */
    private function stateUrl(Request $request): string
    {
        $secret = $request->route('secret');

        if (is_string($secret) && $secret !== '') {
            return route('kitchen.state.secret', ['secret' => $secret]);
        }

        $query = $request->query('secret');

        return route('kitchen.state', is_string($query) && $query !== '' ? ['secret' => $query] : []);
    }
}
