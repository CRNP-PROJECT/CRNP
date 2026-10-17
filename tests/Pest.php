<?php

declare(strict_types=1);

use App\Auth\RtdbUser;
use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RestRtdbClient;
use App\Rtdb\ServiceAccountTokenProvider;
use App\Staff\StaffDirectory;
use App\Staff\StaffRole;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\CountingRtdbClient;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

const RTDB_BASE_URL = 'https://crnp-test.firebaseio.com';

const RTDB_TOKEN_URL = 'https://oauth2.googleapis.com/token';

/**
 * Create a temp file that is removed when the test process exits.
 *
 * Tests that hand a real file to an UploadedFile or an RSA key path must not
 * leak it: a proof-image fixture is hundreds of KB, and an unlinked fixture
 * per test filled the tmpfs after a few runs. Registering every path here
 * keeps cleanup in one place, so a new test cannot reintroduce the leak.
 */
function testTempFile(string $prefix, string $suffix = ''): string
{
    static $paths = [];
    static $registered = false;

    if (! $registered) {
        register_shutdown_function(static function () use (&$paths): void {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        });
        $registered = true;
    }

    $created = (string) tempnam(sys_get_temp_dir(), $prefix);
    $paths[] = $created;

    if ($suffix === '') {
        return $created;
    }

    $path = $created . $suffix;

    if (is_file($created)) {
        rename($created, $path);
        $paths[] = $path;
    }

    return $path;
}

/**
 * A one-line service-account JSON with a freshly minted RSA key, so the REST
 * client can sign a real RS256 assertion without any committed secret.
 */
function fakeServiceAccountPath(): string
{
    static $path = null;

    if ($path !== null) {
        return $path;
    }

    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($key === false) {
        throw new RuntimeException('Unable to generate a test RSA key.');
    }

    $privateKey = '';
    openssl_pkey_export($key, $privateKey);

    $path = testTempFile('crnp-sa-');

    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'crnp-test',
        'private_key_id' => 'test-key-id',
        'private_key' => $privateKey,
        'client_email' => 'rtdb-test@crnp-test.iam.gserviceaccount.com',
        'token_uri' => RTDB_TOKEN_URL,
    ], JSON_THROW_ON_ERROR));

    return $path;
}

function restRtdbClient(string $baseUrl = RTDB_BASE_URL): RestRtdbClient
{
    return new RestRtdbClient(
        $baseUrl,
        new ServiceAccountTokenProvider(fakeServiceAccountPath(), RTDB_TOKEN_URL, 3300),
    );
}

/**
 * Fake the token endpoint plus a set of RTDB URL => response pairs.
 *
 * @param  array<string, \Illuminate\Http\Client\Response>  $responses
 */
function fakeRtdb(array $responses): void
{
    Http::fake([
        RTDB_TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        ...$responses,
    ]);
}

/**
 * The query parameters of a sent request, decoded back to their raw values.
 *
 * @return array<string, string>
 */
function httpQuery(Request $request): array
{
    $query = [];
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

function rtdb(): RtdbClient
{
    return app(RtdbClient::class);
}

/**
 * The ids of the tickets the kitchen board currently serves, across both lanes.
 *
 * The board's own read is the seam a test crosses to ask "is this order on the
 * board?" — it applies the same status filter and the same expiry production
 * does, so an assertion cannot pass while the real board has dropped the ticket.
 *
 * @return list<string>
 */
function onKitchenBoard(): array
{
    $snapshot = app(\App\Kitchen\KitchenBoard::class)->snapshot();

    return array_map(
        static fn(\App\Kitchen\KitchenTicket $ticket): string => $ticket->id,
        [...$snapshot->now, ...$snapshot->later],
    );
}

/**
 * Wrap the bound in-memory client in a read-counting double so a test can
 * assert that a page view did not re-read the heavy catalog node.
 *
 * Seed the data first, then call this — the counter wraps the same underlying
 * store, so seeded nodes stay visible while `get()` calls are counted.
 */
function useCountingRtdb(): CountingRtdbClient
{
    $inner = app(RtdbClient::class);
    $counter = new CountingRtdbClient($inner);

    app()->instance(RtdbClient::class, $counter);

    return $counter;
}

/**
 * The business-settings seam the admin surface and the customer site share.
 */
function settings(): \App\Settings\SettingsRepository
{
    return app(\App\Settings\SettingsRepository::class);
}

/**
 * Arrange the `settings` node without stamping `updatedAt`, then evict the
 * cached read so a test can seed, read, and re-seed within one case.
 *
 * @param  array<string, mixed>  $settings
 */
function seedSettings(array $settings = []): void
{
    rtdb()->seed('settings', $settings);

    settings()->evict();
}

/**
 * Arrange the `stats/top3` node the way #31 will write it: a ranked list of
 * `{productId}` rows. Reading it is #22's job; computing it is #31's.
 *
 * @param  list<string>  $productIds
 */
function seedTopThree(array $productIds): void
{
    rtdb()->seed('stats/top3', [
        'rankings' => array_map(
            static fn(string $id): array => ['productId' => $id],
            $productIds,
        ),
    ]);
}

/**
 * Arrange a favorite exactly as the toggle action writes it.
 */
function seedFavorite(string $uid, string $productId): void
{
    rtdb()->update("users/{$uid}/favorites", [$productId => true]);
}

/**
 * The weekly opening window used by the customer-site tests, in Asia/Manila.
 *
 * @return array<string, array{open: string, close: string}>
 */
function weekdayHours(): array
{
    return [
        'mon' => ['open' => '09:00', 'close' => '21:00'],
        'tue' => ['open' => '09:00', 'close' => '21:00'],
        'wed' => ['open' => '09:00', 'close' => '21:00'],
        'thu' => ['open' => '09:00', 'close' => '21:00'],
        'fri' => ['open' => '09:00', 'close' => '21:00'],
        'sat' => ['open' => '09:00', 'close' => '21:00'],
        'sun' => ['open' => '09:00', 'close' => '21:00'],
    ];
}

/**
 * Sign a request in as a Google account without running the OAuth exchange:
 * the guard resolves the staff row from the signed-in email, so tests only
 * need the identity the session carries. The account is signed into all three
 * areas' guards, so one helper serves customer, cashier, and admin specs.
 */
function signIn(string $email, ?string $name = null, string $sub = 'sub-1'): RtdbUser
{
    $user = new RtdbUser($sub, $email, $name ?? $email);

    foreach (['web', 'admin', 'cashier'] as $guard) {
        test()->actingAs($user, $guard);
    }

    return $user;
}

/**
 * Sign out of every area — the inverse of {@see signIn()} — so a spec can drop
 * back to a fully anonymous visitor between role steps.
 */
function signOut(): void
{
    foreach (['web', 'admin', 'cashier'] as $guard) {
        app('auth')->guard($guard)->logout();
    }
}

/**
 * Arrange a staff row exactly as the seeder or the admin create action would,
 * keyed by the derived staff id, and return that id.
 */
function seedStaff(string $email, StaffRole $role, string $name = 'Staff'): string
{
    $id = StaffDirectory::idForEmail($email);

    rtdb()->seed("staff/{$id}", [
        'name' => $name,
        'email' => $email,
        'role' => $role->value,
    ]);

    return $id;
}

/**
 * Arrange a category node exactly as the admin create action would.
 */
function seedCategory(string $id, string $name, int $order = 0): string
{
    rtdb()->seed("categories/{$id}", ['name' => $name, 'order' => $order]);

    return $id;
}

/**
 * Arrange a product node directly — for tests that need a starting menu without
 * driving the create form.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedProduct(string $id, array $overrides = []): string
{
    rtdb()->seed("products/{$id}", [
        'name' => 'Milktea',
        'description' => 'Classic',
        'price' => 120.0,
        'categoryId' => 'drinks',
        'imageBase64' => '',
        'hidden' => false,
        ...$overrides,
    ]);

    return $id;
}

/**
 * The single stored product, keyed by its generated id.
 *
 * @return array{id: string, node: array<string, mixed>}
 */
function onlyProduct(): array
{
    $products = rtdb()->get('products');

    expect($products)->toBeArray()->toHaveCount(1);

    $id = (string) array_key_first($products);

    return ['id' => $id, 'node' => $products[$id]];
}

/**
 * Arrange a reservation node exactly as the create action would.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedReservation(string $id, array $overrides = []): string
{
    rtdb()->seed("reservations/{$id}", [
        'type' => 'dine_in',
        'date' => '2026-10-08',
        'time' => '18:00',
        'party' => 2,
        'name' => 'Ana Cruz',
        'phone' => '09171234567',
        'status' => 'confirmed',
        'preferredType' => 'dine_in',
        ...$overrides,
    ]);

    return $id;
}

/**
 * Arrange a `sales/{id}` row exactly as Mark served / Settle walk-in / the
 * close sweep writes it — the revenue row the dashboard, reports, and export
 * read (#32).
 *
 * @param  array<string, mixed>  $overrides
 */
function seedSale(string $id, array $overrides = []): string
{
    rtdb()->seed("sales/{$id}", [
        'code' => 'SALE1',
        'type' => 'walkin',
        'lines' => [],
        'total' => 100.0,
        'payments' => [['method' => 'cash', 'amount' => 100.0]],
        'soldAt' => now('UTC')->toIso8601ZuluString(),
        ...$overrides,
    ]);

    return $id;
}

/**
 * Arrange a rental item node exactly as the admin create action writes it
 * (#34): name · image · price/day · units.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedRentalItem(string $id, array $overrides = []): string
{
    rtdb()->seed("rentals/{$id}", [
        'name' => 'Folding chair',
        'imageBase64' => '',
        'pricePerDay' => 50.0,
        'units' => 3,
        ...$overrides,
    ]);

    return $id;
}

/**
 * Arrange an `orders/{id}` row for the admin reports (#32) — a rejected or
 * voided order carrying an outstanding `refundOwed`.
 *
 * @param  array<string, mixed>  $overrides
 */
function seedAdminOrder(string $id, array $overrides = []): string
{
    rtdb()->seed("orders/{$id}", [
        'code' => 'ORD1',
        'type' => 'online',
        'status' => 'rejected',
        'lines' => [],
        'total' => 400.0,
        'payments' => [],
        'change' => 0.0,
        'placedAt' => '2026-10-06T04:00:00Z',
        'rejectedAt' => '2026-10-06T04:10:00Z',
        'enteredAmount' => 350.0,
        'refundOwed' => 350.0,
        'gcashRef' => 'GC999',
        ...$overrides,
    ]);

    return $id;
}

/**
 * Arrange a rental booking node exactly as the booking action writes it (#34).
 *
 * @param  array<string, mixed>  $overrides
 */
function seedRentalBooking(string $id, array $overrides = []): string
{
    rtdb()->seed("rentalBookings/{$id}", [
        'code' => 'RNT1',
        'itemId' => 'r1',
        'uid' => 'sub-ada',
        'dateFrom' => '2026-10-08',
        'dateTo' => '2026-10-10',
        'qty' => 1,
        'total' => 150.0,
        'status' => 'confirmed',
        'payments' => [],
        'returnDueAt' => '2026-10-10T15:59:59Z',
        'createdAt' => '2026-10-06T02:00:00Z',
        ...$overrides,
    ]);

    return $id;
}

/**
 * The distinct form field names a person actually supplies on a screen — hidden
 * inputs (CSRF, method spoof, wizard carry-over) and submit controls excluded.
 *
 * @return list<string>
 */
function visibleFieldNames(string $html): array
{
    preg_match_all('/<(?:input|select|textarea)\b[^>]*>/i', $html, $matches);

    $names = [];

    foreach ($matches[0] as $tag) {
        if (preg_match('/type="hidden"/i', $tag) === 1) {
            continue;
        }

        if (preg_match('/name="([^"]+)"/i', $tag, $name) === 1) {
            $names[$name[1]] = true;
        }
    }

    return array_keys($names);
}
