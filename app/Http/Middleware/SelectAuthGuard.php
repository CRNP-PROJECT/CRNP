<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\AuthArea;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Select the auth guard from the URL prefix.
 *
 * `/admin` resolves the admin guard, `/cashier` the cashier guard, and every
 * other path the customer guard. Because each guard keeps its own session key,
 * the three identities coexist in one session — signing in to one area never
 * signs the others out. The default guard is set before the `auth` middleware
 * runs so `$request->user()` and the role guard read the area's identity.
 */
final readonly class SelectAuthGuard
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse(match (true) {
            $request->is('admin', 'admin/*') => AuthArea::Admin->guard(),
            $request->is('cashier', 'cashier/*') => AuthArea::Cashier->guard(),
            default => AuthArea::Customer->guard(),
        });

        return $next($request);
    }
}
