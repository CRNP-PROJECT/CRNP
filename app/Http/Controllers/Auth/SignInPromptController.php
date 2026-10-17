<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\AuthArea;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The sign-in prompt: the single "Sign in with Google" button a gated action
 * surfaces, and the landing pad for a signed-out visitor. It is area-aware, so
 * `/login`, `/admin/login`, and `/cashier/login` each point the one button at
 * the Google redirect for their own guard.
 */
final class SignInPromptController extends Controller
{
    public function __invoke(): View
    {
        $area = AuthArea::fromStored(request()->route('area'));

        return view('auth.login', [
            'area' => $area,
            'redirectUrl' => route('auth.google.redirect', $area === AuthArea::Customer ? [] : ['area' => $area->value]),
        ]);
    }
}
