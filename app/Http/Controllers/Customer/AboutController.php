<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The public about / branches page — where to find the diner. Static content,
 * no RTDB read, so it never touches the catalog cache.
 */
final class AboutController extends Controller
{
    public function __invoke(): View
    {
        return view('menu.about');
    }
}
