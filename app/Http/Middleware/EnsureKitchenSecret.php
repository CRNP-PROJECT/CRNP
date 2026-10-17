<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shared-secret gate for the kitchen board (PLAN §4 *Kitchen board*).
 *
 * The kitchen is a **kiosk-level** surface with no login: access is the secret
 * from settings (#20), presented either as a path segment (`/kitchen/{secret}`)
 * or a query string (`/kitchen?secret=`). The comparison is constant-time, and
 * a missing/blank configured secret denies every request — so a diner that has
 * never rotated one has no open board. Rotating the secret (#20) replaces the
 * stored value, so the old URL stops matching on the next request with no
 * redeploy.
 *
 * The cook has no session and no interaction; this middleware only answers
 * "is this URL the current secret?".
 */
final readonly class EnsureKitchenSecret
{
    public function __construct(private SettingsRepository $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->matches($request)) {
            abort(404);
        }

        return $next($request);
    }

    private function matches(Request $request): bool
    {
        $expected = trim($this->settings->current()->kitchenSecret);

        if ($expected === '') {
            return false;
        }

        $presented = $request->route('secret');
        $presented = is_string($presented) ? $presented : $request->query('secret');

        if (! is_string($presented) || $presented === '') {
            return false;
        }

        return hash_equals($expected, $presented);
    }
}
