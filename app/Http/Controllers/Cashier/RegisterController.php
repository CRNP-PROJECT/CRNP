<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Staff\StaffDirectory;
use Illuminate\Contracts\View\View;

/**
 * The cashier register shell.
 *
 * The register itself (walk-in ring-up, the online pickup queue, reservations,
 * rentals) arrives with the later cashier slices; this slice establishes the
 * register header, which names the cashier on duty from `staff/{uid}`.
 */
final class RegisterController extends Controller
{
    public function __construct(private readonly StaffDirectory $directory) {}

    public function __invoke(): View
    {
        return view('cashier.register', [
            'cashier' => $this->directory->forUser(auth()->user()),
        ]);
    }
}
