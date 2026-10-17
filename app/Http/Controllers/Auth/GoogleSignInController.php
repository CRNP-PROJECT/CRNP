<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\Actions\ProvisionCustomer;
use App\Auth\AuthArea;
use App\Auth\GoogleIdentity;
use App\Auth\GoogleOAuth;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * The one "Sign in with Google" button, area-aware.
 *
 * There is no sign-up form, no OTP, and no password reset — Google is the only
 * credential, and a first sign-in auto-provisions `users/{uid}`. Which guard it
 * signs into is the area the prompt came from: the customer site, `/admin`, or
 * `/cashier`. On the callback the area is read back from the session, so one
 * shared Google redirect URI serves all three without touching Google's console.
 */
final class GoogleSignInController extends Controller
{
    /**
     * The session key remembering which area began the OAuth exchange.
     */
    private const AREA_KEY = 'auth.area';

    public function __construct(
        private readonly GoogleOAuth $google,
        private readonly ProvisionCustomer $provisionCustomer,
    ) {}

    /**
     * Send the visitor to Google for the area named on the route (the customer
     * area when it is omitted), remembering the area for the callback.
     */
    public function redirect(?string $area = null): RedirectResponse
    {
        session()->put(self::AREA_KEY, AuthArea::fromStored($area)->value);

        return $this->google->redirect();
    }

    /**
     * Verify Google's callback, provision or reuse `users/{uid}`, and sign the
     * identity into the guard for the area the exchange began in.
     *
     * The one-time `state` is consumed on first use, so a callback replayed by a
     * refresh, a back button, an old tab, or a second parallel sign-in arrives
     * with no matching state. That is an ordinary stale link, not a server
     * fault: send the visitor back to the area's sign-in to start again rather
     * than surfacing a 500.
     */
    public function callback(): RedirectResponse
    {
        $area = AuthArea::fromStored(session()->pull(self::AREA_KEY));

        try {
            $identity = $this->identityFrom($this->google->user());
        } catch (InvalidStateException) {
            return redirect()->route($area->loginRoute())
                ->with('status', 'Your sign-in link expired. Please try again.');
        }

        $customer = ($this->provisionCustomer)($identity);

        Auth::guard($area->guard())->login($customer);

        return redirect()->intended(route($area->homeRoute()));
    }

    /**
     * Sign out of the area the request belongs to, leaving every other area's
     * sign-in untouched.
     */
    public function logout(): RedirectResponse
    {
        $area = AuthArea::fromStored(Auth::getDefaultDriver());

        Auth::guard($area->guard())->logout();

        return redirect()->route($area->logoutRoute());
    }

    private function identityFrom(SocialiteUser $googleUser): GoogleIdentity
    {
        return new GoogleIdentity(
            sub: (string) $googleUser->getId(),
            email: $googleUser->getEmail(),
            name: $googleUser->getName(),
            avatar: $googleUser->getAvatar(),
        );
    }
}
