<?php

declare(strict_types=1);

use App\Auth\AuthArea;
use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\BackupDownloadController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HistoricalSalesController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RentalController as AdminRentalController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\GoogleSignInController;
use App\Http\Controllers\Auth\SignInPromptController;
use App\Http\Controllers\Cashier\QueueController;
use App\Http\Controllers\Cashier\RegisterController;
use App\Http\Controllers\Cashier\RentalController;
use App\Http\Controllers\Cashier\ReservationController;
use App\Http\Controllers\Cashier\WalkInController;
use App\Http\Controllers\Customer\AboutController;
use App\Http\Controllers\Customer\AddOnPrefsController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\FavoriteToggleController;
use App\Http\Controllers\Customer\MenuController;
use App\Http\Controllers\Customer\OccupancyController;
use App\Http\Controllers\Customer\OrderTrackerController;
use App\Http\Controllers\Customer\ProductPageController;
use App\Http\Controllers\Customer\ProofController;
use App\Http\Controllers\Customer\RentalBookingController;
use App\Http\Controllers\Customer\RentalCatalogController;
use App\Http\Controllers\Kitchen\KitchenBoardController;
use Illuminate\Support\Facades\Route;

// The public site: the menu and product pages serve from the coalesced catalog
// cache (PLAN §4 Quota discipline, rule 2), so a page view never re-reads the
// base64 product images. Browsing never requires a sign-in.
Route::get('/', MenuController::class)->name('home');
Route::get('/menu/{product}', ProductPageController::class)->name('menu.product');
Route::get('/about', AboutController::class)->name('about');

// The public read-only occupancy view: seats taken vs capacity per slot for the
// week ahead, confirmed bookings only, aggregate only — it books nothing.
Route::get('/occupancy', OccupancyController::class)->name('occupancy');

// The public rental catalog and item pages: read-only price/day and computed
// per-date availability, behind the same open/closed badge as place-order.
// Booking itself is sign-in-gated on the item page (PLAN §4 *Rentals*).
Route::get('/rentals', RentalCatalogController::class)->name('rentals.index');
Route::get('/rentals/{item}', [RentalBookingController::class, 'show'])->name('rentals.show');

// Gated per-account actions: the heart and the saved add-on selection prompt
// the Google button while signed out (browsing never does) and sync through
// `users/{uid}`, so they follow the customer across devices. The cart is gated
// the same way — building one requires a live session.
Route::middleware('auth')->group(function (): void {
    Route::post('/menu/{product}/favorite', FavoriteToggleController::class)->name('favorites.toggle');
    Route::post('/menu/{product}/add-ons', AddOnPrefsController::class)->name('prefs.add-ons');

    // Cart: inline steppers and add-on picks; totals recompute server-side.
    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('/cart', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');

    // Order tracker: a live status screen bound to the session and
    // `users/{uid}`, reopenable with zero typing; the code is shown, never typed.
    Route::get('/orders', [OrderTrackerController::class, 'reopen'])->name('orders.tracker');
    Route::get('/orders/{order}', [OrderTrackerController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/state', [OrderTrackerController::class, 'state'])->name('orders.state');

    // Proof of payment: an in-flight marker when the upload begins, then the
    // screenshot + amount + ref#. The server re-encodes the image and
    // auto-verifies (or flags the hold); the client re-polls the tracker.
    Route::post('/orders/{order}/proof', [ProofController::class, 'store'])->name('orders.proof.store');
    Route::post('/orders/{order}/proof/start', [ProofController::class, 'start'])->name('orders.proof.start');

    // Rental booking: a date range + quantity stepper, the total computed
    // server-side, then the SAME GCash gate as orders (proof attach, amount/ref
    // flag, 15-minute window). Prepaid in full; no deposit split. The
    // account-bound tracker reopens with zero typing and lives under its own
    // prefix so it never collides with the public `/rentals/{item}` item page.
    Route::post('/rentals/{item}/book', [RentalBookingController::class, 'store'])->name('rentals.book');
    Route::get('/my-rentals', [RentalBookingController::class, 'mine'])->name('rentals.mine');
    Route::get('/my-rentals/{booking}', [RentalBookingController::class, 'track'])->name('rentals.track');
    Route::get('/my-rentals/{booking}/state', [RentalBookingController::class, 'state'])->name('rentals.state');
    Route::post('/my-rentals/{booking}/proof', [RentalBookingController::class, 'proof'])->name('rentals.proof.store');
    Route::post('/my-rentals/{booking}/proof/start', [RentalBookingController::class, 'proofStart'])->name('rentals.proof.start');
});

// Checkout: pickup time (default ASAP, earliest now + 15 min) then Place order.
// The POST is validated and throttled (10/min per user, 30/min per real IP for
// anonymous) and carries a client-generated idempotency key. Sign-in is
// enforced in the controller, not by `auth`, so the throttle counts anonymous
// traffic before the sign-in redirect (PLAN §4 *Auth & roles*).
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:checkout')
    ->name('checkout.store');

// The one sign-in surface per area: a Google button, never a form. Browsing
// stays open; only ordering, favoriting, and preference sync route a visitor
// here. `/admin/login` and `/cashier/login` show the same button aimed at their
// own guard — each area keeps its own session key, so signing in to one area
// never signs the others out.
Route::get('/login', SignInPromptController::class)->name('login');
Route::get('/admin/login', SignInPromptController::class)
    ->defaults('area', AuthArea::Admin->value)->name('admin.login');
Route::get('/cashier/login', SignInPromptController::class)
    ->defaults('area', AuthArea::Cashier->value)->name('cashier.login');

Route::get('/auth/google/redirect/{area?}', [GoogleSignInController::class, 'redirect'])
    ->whereIn('area', [AuthArea::Admin->value, AuthArea::Cashier->value])
    ->middleware('throttle:login')
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleSignInController::class, 'callback'])
    ->middleware('throttle:login')
    ->name('auth.google.callback');

// Sign out of one area only: the customer header, the admin nav, and the
// register header each post to their own route, so the other areas stay signed
// in (Switch account on a shared register = this area's sign-out, then sign in).
Route::post('/logout', [GoogleSignInController::class, 'logout'])->name('logout');
Route::post('/admin/logout', [GoogleSignInController::class, 'logout'])->name('admin.logout');
Route::post('/cashier/logout', [GoogleSignInController::class, 'logout'])->name('cashier.logout');

// Staff areas: the role is read from `staff/{uid}` per request, so a Google
// account absent from the staff list is refused and a demotion takes effect on
// the next request. `/cashier` is for cashiers, `/admin` for admins only.
Route::middleware(['auth', 'staff.role:cashier'])->group(function (): void {
    Route::get('/cashier', RegisterController::class)->name('cashier.register');

    // The online pickup queue: prepaid orders arrive ready-to-pay (no approval
    // tap), verified orders land in the recently-verified list (Reject reachable
    // until Mark served), and in-window orders with no screenshot show as
    // awaiting proof. The read is coalesced behind a cache entry, and Mark
    // served is the one happy-path tap — a stale or duplicate tap is a no-op.
    // #27 adds Reject / Approve / Void; #29 adds the Dismissed (Restore / Void)
    // and Unclaimed (Collect late) taps; every one is status-guarded.
    Route::get('/cashier/queue', [QueueController::class, 'index'])->name('cashier.queue');
    Route::get('/cashier/queue/state', [QueueController::class, 'state'])->name('cashier.queue.state');
    Route::post('/cashier/orders/{order}/serve', [QueueController::class, 'serve'])->name('cashier.orders.serve');
    Route::post('/cashier/orders/{order}/reject', [QueueController::class, 'reject'])->name('cashier.orders.reject');
    Route::post('/cashier/orders/{order}/approve', [QueueController::class, 'approve'])->name('cashier.orders.approve');
    Route::post('/cashier/orders/{order}/restore', [QueueController::class, 'restore'])->name('cashier.orders.restore');
    Route::post('/cashier/orders/{order}/void', [QueueController::class, 'void'])->name('cashier.orders.void');
    Route::post('/cashier/orders/{order}/collect-late', [QueueController::class, 'collectLate'])->name('cashier.orders.collect-late');

    // Walk-in ring-up: tap tiles → one typed cash number → receipt. The order
    // draws its code at ring-up and only reaches the kitchen board at settle.
    Route::prefix('cashier/walkins')->name('cashier.walkins.')->group(function (): void {
        Route::post('/', [WalkInController::class, 'store'])->name('store');
        Route::get('/{walkin}', [WalkInController::class, 'show'])->name('show');
        Route::post('/{walkin}/items', [WalkInController::class, 'addItem'])->name('items.store');
        Route::delete('/{walkin}/items/{product}', [WalkInController::class, 'removeItem'])->name('items.destroy');
        Route::post('/{walkin}/settle', [WalkInController::class, 'settle'])->name('settle');
        Route::get('/{walkin}/receipt', [WalkInController::class, 'receipt'])->name('receipt');
    });

    // Reservation entry is lookup-first: the phone typeahead prefills a
    // returning customer; a new customer gets a guided two-step capture. The
    // server-run conflict and duplicate check blocks a double-booking with a
    // reason before the write and again on confirm.
    Route::get('/cashier/reservations', [ReservationController::class, 'index'])->name('cashier.reservations.index');
    Route::get('/cashier/reservations/new', [ReservationController::class, 'create'])->name('cashier.reservations.create');
    Route::get('/cashier/reservations/lookup', [ReservationController::class, 'lookup'])->name('cashier.reservations.lookup');
    Route::post('/cashier/reservations/details', [ReservationController::class, 'details'])->name('cashier.reservations.details');
    Route::post('/cashier/reservations', [ReservationController::class, 'store'])->name('cashier.reservations.store');
    Route::post('/cashier/reservations/{reservation}/confirm', [ReservationController::class, 'confirm'])->name('cashier.reservations.confirm');
    Route::post('/cashier/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('cashier.reservations.cancel');

    // Rental counter: Hand over lowers stock, Confirm return raises it, a
    // flagged proof offers one-tap Confirm / Cancel, and a quiet overdue row
    // sits past the return-due date with no penalty.
    Route::get('/cashier/rentals', [RentalController::class, 'index'])->name('cashier.rentals.index');
    Route::post('/cashier/rentals/{booking}/confirm-proof', [RentalController::class, 'confirmProof'])->name('cashier.rentals.confirm-proof');
    Route::post('/cashier/rentals/{booking}/cancel', [RentalController::class, 'cancel'])->name('cashier.rentals.cancel');
    Route::post('/cashier/rentals/{booking}/hand-over', [RentalController::class, 'handOver'])->name('cashier.rentals.hand-over');
    Route::post('/cashier/rentals/{booking}/confirm-return', [RentalController::class, 'confirmReturn'])->name('cashier.rentals.confirm-return');
});

// The kitchen read-only board: a kiosk-level, shared-secret URL with no login
// and zero cook interaction. The gate is the secret from settings (#20), which
// accepts either a path segment or a query string; rotating the secret kills
// the old URL. The board serves NOW/LATER from the coalesced snapshot, so ten
// kiosks cost one RTDB read per 5 s interval.
Route::middleware('kitchen.secret')->group(function (): void {
    Route::get('/kitchen', [KitchenBoardController::class, 'board'])->name('kitchen.board');
    Route::get('/kitchen/state', [KitchenBoardController::class, 'state'])->name('kitchen.state');
    Route::get('/kitchen/{secret}', [KitchenBoardController::class, 'board'])->name('kitchen.board.secret');
    Route::get('/kitchen/{secret}/state', [KitchenBoardController::class, 'state'])->name('kitchen.state.secret');
});

Route::middleware(['auth', 'staff.role:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

    // Categories — the admin-managed grouping products reference by id.
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Products — CRUD, the one-tap hide toggle (86 board), and the one-click
    // Add promo control. The product form stays promo-free.
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::put('/products/{product}/visibility', [ProductController::class, 'visibility'])->name('products.visibility');
    Route::post('/products/{product}/promo', [ProductController::class, 'addPromo'])->name('products.promo.store');
    Route::delete('/products/{product}/promo', [ProductController::class, 'removePromo'])->name('products.promo.destroy');

    // Rental inventory — rental units only. Item CRUD (name · image · price/day
    // · units) sits next to Add stock (supplier · qty · date) and the inline
    // − / + stepper; the low-stock list carries the restock button.
    Route::get('/rentals', [AdminRentalController::class, 'index'])->name('rentals.index');
    Route::get('/rentals/create', [AdminRentalController::class, 'create'])->name('rentals.create');
    Route::post('/rentals', [AdminRentalController::class, 'store'])->name('rentals.store');
    Route::get('/rentals/{item}/edit', [AdminRentalController::class, 'edit'])->name('rentals.edit');
    Route::put('/rentals/{item}', [AdminRentalController::class, 'update'])->name('rentals.update');
    Route::delete('/rentals/{item}', [AdminRentalController::class, 'destroy'])->name('rentals.destroy');
    Route::post('/rentals/{item}/adjust', [AdminRentalController::class, 'adjust'])->name('rentals.adjust');
    Route::post('/rentals/{item}/stock', [AdminRentalController::class, 'stock'])->name('rentals.stock');

    // Business settings. Each write goes through an action that persists the
    // whole `settings` node and evicts the catalog cache, so a force-close
    // reaches the customer site on the next request.
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings/hours', [SettingsController::class, 'updateHours'])->name('settings.hours');
    Route::put('/settings/banner', [SettingsController::class, 'updateBanner'])->name('settings.banner');
    Route::post('/settings/closures', [SettingsController::class, 'storeClosure'])->name('settings.closures.store');
    Route::delete('/settings/closures/{closure}', [SettingsController::class, 'destroyClosure'])->name('settings.closures.destroy');
    Route::post('/settings/payment', [SettingsController::class, 'updatePayment'])->name('settings.payment');
    Route::post('/settings/kitchen-secret', [SettingsController::class, 'rotateKitchenSecret'])->name('settings.kitchen-secret');
    Route::put('/settings/capacities', [SettingsController::class, 'updateCapacities'])->name('settings.capacities');

    // Reports — sales, payments, reservations, and rental bookings, each
    // filtered by date, plus the refunds-owed column and the admin-only Mark
    // refunded. The rental shape is #34's; until it lands the report reads it
    // defensively.
    Route::get('/reports/sales', [ReportsController::class, 'sales'])->name('reports.sales');
    Route::get('/reports/payments', [ReportsController::class, 'payments'])->name('reports.payments');
    Route::get('/reports/reservations', [ReportsController::class, 'reservations'])->name('reports.reservations');
    Route::get('/reports/rentals', [ReportsController::class, 'rentals'])->name('reports.rentals');
    Route::post('/reports/refunds/{order}/mark', [ReportsController::class, 'markRefunded'])->name('reports.refunds.mark');

    // Historical sales encoding — an admin tool that records past sales into the
    // `sales/{id}` ledger on a chosen date. The `historical.sales` gate answers
    // 404 when the feature is off.
    Route::middleware('historical.sales')->group(function (): void {
        Route::get('/historical', [HistoricalSalesController::class, 'create'])->name('historical.create');
        Route::post('/historical', [HistoricalSalesController::class, 'store'])->name('historical.store');
    });

    // Activity — the bounded feed (time · who · action · order code) plus the
    // per-cashier daily totals grouped by `handledBy`.
    Route::get('/activity', ActivityController::class)->name('activity');

    // Download the newest export-backup (admin-only).
    Route::get('/backup/download', BackupDownloadController::class)->name('backup.download');
});
