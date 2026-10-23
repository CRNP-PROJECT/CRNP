<?php

declare(strict_types=1);

use App\Cache\CatalogCache;
use Illuminate\Support\Facades\Cache;

/**
 * #22 — customer menu and browse.
 *
 * The public menu groups products by category, filters by a category dropdown,
 * renders each card from the coalesced catalog cache (never re-reading the
 * base64 images), and computes the promo strike-through and "% off". The
 * open/closed badge and the place-order gate follow #20's StoreStatus and the
 * Complete-setup rule; favorites and saved add-on prefs ride on `users/{uid}`.
 */

it('groups the menu under category titles in the admin order', function (): void {
    seedCategory('drinks', 'Drinks', 1);
    seedCategory('mains', 'Mains', 2);
    seedProduct('p1', ['name' => 'Milktea', 'categoryId' => 'drinks']);
    seedProduct('p2', ['name' => 'Adobo', 'categoryId' => 'mains']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<h2 class="menu-group__title" id="menu-group-drinks">Drinks</h2>', false)
        ->assertSee('<h2 class="menu-group__title" id="menu-group-mains">Mains</h2>', false)
        ->assertSeeInOrder(['Milktea', 'Adobo'])
        ->assertSee('category-menu', false);
});

it('filters the menu to one category when it is picked from the dropdown', function (): void {
    seedCategory('drinks', 'Drinks', 1);
    seedCategory('mains', 'Mains', 2);
    seedProduct('p1', ['name' => 'Milktea', 'categoryId' => 'drinks']);
    seedProduct('p2', ['name' => 'Adobo', 'categoryId' => 'mains']);

    $this->get('/?category=drinks')
        ->assertOk()
        ->assertSee('Milktea')
        ->assertDontSee('Adobo')
        ->assertDontSee('id="menu-group-mains"', false)
        ->assertSee('category-menu__value', false)
        ->assertSee('Drinks');
});

it('groups a product with no matching category under All Products', function (): void {
    seedCategory('drinks', 'Drinks', 1);
    seedProduct('p1', ['name' => 'Milktea', 'categoryId' => 'drinks']);
    seedProduct('p2', ['name' => 'Mystery Special', 'categoryId' => 'ghost']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<h2 class="menu-group__title" id="menu-group-all">All Products</h2>', false)
        ->assertSee('Mystery Special');
});

it('shows an empty state when the menu has no products', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('The menu is being set up');
});

it('shows the product image, price, promo and Top 3 tags and a favorite heart', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea', 'price' => 250.0, 'imageBase64' => 'IMAGEDATA']);
    seedTopThree(['p1']);

    $this->get('/')
        ->assertOk()
        ->assertSee('data:image/jpeg;base64,IMAGEDATA', false)
        ->assertSee('₱250.00')
        ->assertSee('Top 3')
        ->assertSee('favorite-toggle');
});

it('strikes through the base price and computes the "% off" on a promo card', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', [
        'name' => 'Milktea',
        'price' => 250.0,
        'promoMode' => 'percent',
        'promoValue' => 20.0,
        'promoPrice' => 200.0,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<s>₱250.00</s>', false)
        ->assertSee('₱200.00')
        ->assertSee('20% off');
});

it('shows no Top 3 tag when the ranking is absent', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea']);

    $this->get('/')->assertOk()->assertDontSee('Top 3');
});

it('shows the open badge when the store is within its hours', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1');
    seedSettings(['hours' => weekdayHours()]);

    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));

    $this->get('/')->assertOk()->assertSee('Open now');
});

it('shows a closed badge when the force-close toggle is on', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1');
    seedSettings(['hours' => weekdayHours(), 'forceClose' => true]);

    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));

    $this->get('/')->assertOk()->assertSee('Closed');
});

it('shows a closed badge on a date inside a scheduled closure', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1');
    seedSettings([
        'hours' => weekdayHours(),
        'closures' => [
            'christmas' => ['from' => '2026-12-24', 'to' => '2026-12-26', 'label' => 'Christmas'],
        ],
    ]);

    $this->travelTo(new DateTimeImmutable('2026-12-25 12:00:00', new DateTimeZone('Asia/Manila')));

    $this->get('/')->assertOk()->assertSee('Closed')->assertSee('Christmas');
});

it('disables place-order while closed and states the reason', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1');
    seedSettings(['hours' => weekdayHours()]);

    // 08:00 Manila on a trading day is before opening.
    $this->travelTo(new DateTimeImmutable('2026-10-06 08:00:00', new DateTimeZone('Asia/Manila')));

    $this->get('/')
        ->assertOk()
        ->assertSee('Start an order')
        ->assertSee('<button class="primary-button" type="button" disabled', false)
        ->assertSee('Ordering is closed right now.');
});

it('disables place-order until the GCash number and QR are configured, with a reason', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1');
    seedSettings(['hours' => weekdayHours()]);

    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));

    $this->get('/')
        ->assertOk()
        ->assertSee('<button class="primary-button" type="button" disabled', false)
        ->assertSee('GCash number and QR');
});

it('enables place-order when open and the payment block is configured', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1');
    seedSettings([
        'hours' => weekdayHours(),
        'gcash' => ['number' => '0917 123 4567', 'qr' => 'data:image/jpeg;base64,QR'],
    ]);

    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));

    $this->get('/')
        ->assertOk()
        ->assertSee('<a class="primary-button" href="#menu">Start an order</a>', false);
});

it('shows the announcement banner when it is visible', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1');
    seedSettings(['banner' => ['text' => 'Buy 1 take 1 this weekend!', 'visible' => true]]);

    $this->get('/')->assertOk()->assertSee('Buy 1 take 1 this weekend!');
});

it('hides the announcement banner when the toggle is off', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1');
    seedSettings(['banner' => ['text' => 'Quiet week', 'visible' => false]]);

    $this->get('/')->assertOk()->assertDontSee('Quiet week');
});

it('persists a favorite for a signed-in customer across requests', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea']);

    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    $this->post(route('favorites.toggle', ['product' => 'p1']))->assertRedirect();

    expect(rtdb()->get('users/sub-ada/favorites/p1'))->toBeTrue();

    $this->get('/')->assertOk()->assertSee('aria-pressed="true"', false);

    // A second device (a fresh request on the same account) sees the favorite.
    $this->get('/')->assertOk()->assertSee('aria-pressed="true"', false);

    // A second tap on another device unsets it.
    $this->post(route('favorites.toggle', ['product' => 'p1']))->assertRedirect();

    expect(rtdb()->get('users/sub-ada/favorites/p1'))->toBeNull();

    $this->get('/')->assertOk()->assertSee('aria-pressed="false"', false);
});

it('keeps favorites per account', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea']);

    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $this->post(route('favorites.toggle', ['product' => 'p1']));

    auth()->logout();
    signIn('grace@example.com', 'Grace Hopper', 'sub-grace');

    $this->get('/')->assertOk()->assertSee('aria-pressed="false"', false);
});

it('prompts the Google button when a signed-out visitor taps the heart', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea']);

    $this->post(route('favorites.toggle', ['product' => 'p1']))
        ->assertRedirect(route('login'));

    $this->get(route('login'))->assertOk()->assertSee('Sign in with Google');
});

it('prompts the Google button when a signed-out visitor saves add-ons', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea']);

    $this->post(route('prefs.add-ons', ['product' => 'p1']), ['addOns' => ['Pearl']])
        ->assertRedirect(route('login'));
});

it('leaves a hidden product off the menu', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Sold Out Milktea', 'hidden' => true]);

    $this->get('/')->assertOk()->assertDontSee('Sold Out Milktea');
});

it('pre-checks a product\'s saved add-on selection on the product page', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', [
        'name' => 'Milktea',
        'addOns' => [
            ['name' => 'Pearl', 'price' => 15.0],
            ['name' => 'Less ice', 'price' => 0.0],
        ],
    ]);

    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');

    $this->post(route('prefs.add-ons', ['product' => 'p1']), ['addOns' => ['Pearl']])
        ->assertRedirect();

    $this->get(route('menu.product', ['product' => 'p1']))
        ->assertOk()
        ->assertSee('value="Pearl" checked', false)
        ->assertDontSee('value="Less ice" checked', false);
});

it('saves the add-on selection per account', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', [
        'name' => 'Milktea',
        'addOns' => [
            ['name' => 'Pearl', 'price' => 15.0],
        ],
    ]);

    signIn('ada@example.com', 'Ada Lovelace', 'sub-ada');
    $this->post(route('prefs.add-ons', ['product' => 'p1']), ['addOns' => ['Pearl']]);

    auth()->logout();
    signIn('grace@example.com', 'Grace Hopper', 'sub-grace');

    $this->get(route('menu.product', ['product' => 'p1']))
        ->assertOk()
        ->assertDontSee('value="Pearl" checked', false);
});

it('serves the menu from the catalog cache without re-reading the product bytes', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea', 'imageBase64' => 'IMAGEDATA']);
    seedSettings(['hours' => weekdayHours()]);

    $client = useCountingRtdb();

    $this->get('/')->assertOk();
    $this->get('/')->assertOk();

    expect($client->reads['products'] ?? 0)->toBe(1);
});

it('serves a product page from the same cached catalog read as the menu', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea', 'imageBase64' => 'IMAGEDATA']);
    seedSettings(['hours' => weekdayHours()]);

    $client = useCountingRtdb();

    $this->get('/')->assertOk();
    $this->get(route('menu.product', ['product' => 'p1']))->assertOk();

    expect($client->reads['products'] ?? 0)->toBe(1);
});

it('re-reads the catalog once an admin product write evicts the cache', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['name' => 'Milktea']);
    seedSettings(['hours' => weekdayHours()]);

    $client = useCountingRtdb();

    $this->get('/')->assertOk();

    expect($client->reads['products'] ?? 0)->toBe(1);

    app(CatalogCache::class)->forget();

    $this->get('/')->assertOk();

    expect($client->reads['products'] ?? 0)->toBe(2)
        ->and(Cache::has(CatalogCache::KEY))->toBeTrue();
});

it('shows a product page with image, price, promo strike-through and details', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', [
        'name' => 'Milktea',
        'description' => 'Classic milk tea',
        'price' => 250.0,
        'imageBase64' => 'IMAGEDATA',
        'promoMode' => 'amount',
        'promoValue' => 200.0,
        'promoPrice' => 200.0,
    ]);

    $this->get(route('menu.product', ['product' => 'p1']))
        ->assertOk()
        ->assertSee('Milktea')
        ->assertSee('Classic milk tea')
        ->assertSee('data:image/jpeg;base64,IMAGEDATA', false)
        ->assertSee('<s>₱250.00</s>', false)
        ->assertSee('₱200.00')
        ->assertSee('20% off');
});

it('returns 404 for a product that is hidden from the menu', function (): void {
    seedCategory('drinks', 'Drinks');
    seedProduct('p1', ['hidden' => true]);

    $this->get(route('menu.product', ['product' => 'p1']))->assertNotFound();
});

it('serves the about page', function (): void {
    $this->get(route('about'))
        ->assertOk()
        ->assertSee('About')
        ->assertSee('Contact Details')
        ->assertSee('Store Hours');
});

it('shows the diner contact, hours, and payment details on the about page', function (): void {
    $this->get(route('about'))
        ->assertOk()
        ->assertSee('cratesnplates@gmail.com')
        ->assertSee('10:00 AM')
        ->assertSee('11:00 PM')
        ->assertSee('GCash Exclusive')
        ->assertSee('https://www.facebook.com/cratesnplatesdiner/');
});
