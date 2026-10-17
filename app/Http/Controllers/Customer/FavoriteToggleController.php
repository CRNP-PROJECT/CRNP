<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Auth\RtdbUser;
use App\Customer\Favorites;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Toggle a product's favorite for the signed-in account.
 *
 * Guarded by `auth`, so a signed-out heart tap is redirected to the one Google
 * button (the #18 sign-in prompt) rather than failing silently — the customer
 * learns why the favorite did not save. The favorite itself syncs per account
 * through `users/{uid}`, so it follows the customer across devices.
 */
final class FavoriteToggleController extends Controller
{
    public function __construct(private readonly Favorites $favorites) {}

    public function __invoke(Request $request, string $product): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof RtdbUser, 403);

        $this->favorites->toggle($user, $product);

        return back();
    }
}
