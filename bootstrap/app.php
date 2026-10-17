<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\EnsureKitchenSecret;
use App\Http\Middleware\EnsureStaffRole;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\SelectAuthGuard;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // In local, normalise the other loopback host onto `app.url` so the
        // Google sign-in's session state cookie and its redirect URI agree.
        // SelectAuthGuard runs first so `/admin`, `/cashier`, and the customer
        // site each resolve their own guard (and so their own session key) from
        // the path before any `auth` check.
        $middleware->web(prepend: [
            SelectAuthGuard::class,
            RedirectToCanonicalHost::class,
        ]);

        // A guest hitting a staff area lands on that area's own sign-in prompt,
        // never the customer one, so the Google button signs into the right guard.
        $middleware->redirectGuestsTo(fn(Request $request): string => match (true) {
            $request->is('admin', 'admin/*') => route('admin.login'),
            $request->is('cashier', 'cashier/*') => route('cashier.login'),
            default => route('login'),
        });

        // The role guard behind `/cashier` and `/admin`: it reads the signed-in
        // account's role from `staff/{uid}` per request (short cache, evicted on
        // any staff write) and refuses a Google account absent from the list.
        $middleware->alias([
            'staff.role' => EnsureStaffRole::class,
            'kitchen.secret' => EnsureKitchenSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
