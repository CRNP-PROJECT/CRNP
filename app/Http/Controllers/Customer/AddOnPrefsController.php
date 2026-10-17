<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Auth\RtdbUser;
use App\Customer\AddOnPrefs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SaveAddOnPrefsRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Save the customer's add-on selection for a product so the product page
 * pre-checks it on the next visit, on any device.
 *
 * Guarded by `auth`: a signed-out selection prompts the Google button, the same
 * as favoriting. The cart (#23) posts here as the customer builds an order.
 */
final class AddOnPrefsController extends Controller
{
    public function __construct(private readonly AddOnPrefs $prefs) {}

    public function __invoke(SaveAddOnPrefsRequest $request, string $product): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof RtdbUser, 403);

        $this->prefs->save($user, $product, $request->addOnNames());

        return back();
    }
}
