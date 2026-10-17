<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins local development to the host configured in `app.url`.
 *
 * The Google flow stores its `state` in the session cookie, and cookies never
 * cross hosts: a sign-in begun on `127.0.0.1` but returned to `localhost` (the
 * registered redirect URI) fails with InvalidStateException. Local visits to
 * the other loopback host are therefore redirected onto the configured one so
 * the tab, the session cookie, and the redirect URI all name the same host.
 *
 * Only loopback aliases (`localhost` / `127.0.0.1` / `::1`) are normalised, and
 * only in `local`, so a configured host, a LAN address, and production are all
 * left exactly as they arrive.
 */
final readonly class RedirectToCanonicalHost
{
    /**
     * @var list<string>
     */
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '::1'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local')) {
            return $next($request);
        }

        $url = config('app.url');

        if (! is_string($url)) {
            return $next($request);
        }

        $canonicalHost = parse_url($url, PHP_URL_HOST);
        $requestHost = $request->getHost();

        if (
            ! is_string($canonicalHost)
            || ! in_array($canonicalHost, self::LOOPBACK_HOSTS, true)
            || ! in_array($requestHost, self::LOOPBACK_HOSTS, true)
            || $requestHost === $canonicalHost
        ) {
            return $next($request);
        }

        return redirect()->to(rtrim($url, '/') . $request->getRequestUri());
    }
}
