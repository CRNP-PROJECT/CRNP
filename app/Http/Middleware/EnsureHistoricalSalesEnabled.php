<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The on/off gate for the **historical sales encoding** surface (see
 * `config/settings.php`).
 *
 * When `settings.historical_sales_enabled` is off, both endpoints answer 404
 * before any request is validated or handled, so the surface vanishes from the
 * running site without a single encoded row being touched — every row it ever
 * wrote stays in the `sales/{id}` ledger.
 */
final readonly class EnsureHistoricalSalesEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('settings.historical_sales_enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
