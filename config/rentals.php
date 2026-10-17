<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rentals (#34)
    |--------------------------------------------------------------------------
    |
    | Inventory is rental units only — menu items carry no stock. These are the
    | smart defaults the rental surfaces pre-fill so the human edits only what
    | differs (PLAN §3), plus the bounds that keep every availability read
    | range-limited rather than whole-tree (PLAN §4 *Quota discipline*, rule 3).
    |
    | - `low_stock_threshold` — a rental item at or below this count joins the
    |   admin's low-stock list and carries the restock button.
    | - `catalog_days` — the length of the public per-date availability window.
    | - `max_booking_days` — the longest range a customer may book, which also
    |   bounds the overlap lookback (a booking can never span more than this).
    |
    */

    'low_stock_threshold' => (int) env('RENTAL_LOW_STOCK_THRESHOLD', 3),

    'catalog_days' => (int) env('RENTAL_CATALOG_DAYS', 7),

    'max_booking_days' => (int) env('RENTAL_MAX_BOOKING_DAYS', 60),

];
