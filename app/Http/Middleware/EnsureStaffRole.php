<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Staff\StaffDirectory;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The role guard for a URL prefix (`/cashier`, `/admin`).
 *
 * It reads the signed-in account's role from `staff/{uid}` per request (behind
 * the directory's short cache) and admits only the roles named on the route.
 * A Google account absent from the staff list — a customer, or one the admin
 * removed — is refused with 403, and because every staff write evicts the
 * cached role, a demotion or deletion ends access on the next request.
 */
final readonly class EnsureStaffRole
{
    public function __construct(private StaffDirectory $directory) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof Authenticatable) {
            abort(403, 'This area is for staff accounts only.');
        }

        $member = $this->directory->forUser($user);

        if ($member === null) {
            abort(403, 'This area is for staff accounts only.');
        }

        // Compare against the raw route arguments so an unknown role name can
        // never widen access — a mistyped guard denies rather than admits.
        if ($roles !== [] && ! in_array($member->role->value, $roles, true)) {
            abort(403, 'Your staff role does not open this area.');
        }

        return $next($request);
    }
}
