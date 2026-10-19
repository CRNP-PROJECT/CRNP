<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\RtdbUserProvider;
use App\Export\ExportBackup;
use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\InMemoryRtdbClient;
use App\Rtdb\RestRtdbClient;
use App\Rtdb\ServiceAccountTokenProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(RtdbClient::class, function (): RtdbClient {
            if ($this->app->environment('testing')) {
                return new InMemoryRtdbClient();
            }

            /** @var string $baseUrl */
            $baseUrl = config('services.firebase.database_url');
            /** @var string $serviceAccountPath */
            $serviceAccountPath = config('services.firebase.service_account_path');
            /** @var ?string $serviceAccountJson */
            $serviceAccountJson = config('services.firebase.service_account_json');
            /** @var string $tokenUri */
            $tokenUri = config('services.firebase.token_uri');
            /** @var int $tokenTtl */
            $tokenTtl = config('services.firebase.token_ttl');

            // The inline JSON exists for hosts without a writable credential
            // file (Render's free plan has no disk); it wins over the path.
            $hasInlineCredential = is_string($serviceAccountJson) && trim($serviceAccountJson) !== '';

            // Local development without the service-account JSON (it is not in
            // version control) must still boot: fall back to the in-memory
            // client so pages render with empty data instead of a 500. Any
            // environment other than `local` still fails loudly on a missing
            // credential, because a silently empty production database is worse.
            if ($this->app->environment('local') && ! $hasInlineCredential && ! is_file($serviceAccountPath)) {
                return new InMemoryRtdbClient();
            }

            return new RestRtdbClient(
                $baseUrl,
                new ServiceAccountTokenProvider(
                    $serviceAccountPath,
                    $tokenUri,
                    $tokenTtl,
                    $hasInlineCredential ? $serviceAccountJson : null,
                ),
            );
        });

        // The export-backup writes its JSON to the local disk (the private
        // `storage/app` tree), which is not a secret store: bind the disk so the
        // service is testable against a faked filesystem.
        $this->app->bind(ExportBackup::class, function (): ExportBackup {
            return new ExportBackup(
                $this->app->make(RtdbClient::class),
                Storage::disk('local'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel's session guard remembers a signed-in customer by the Google
        // `sub`; this provider resolves that identifier back to `users/{uid}`
        // through the one persistence seam, so no SQL user table is needed.
        Auth::provider('rtdb', function (): RtdbUserProvider {
            return new RtdbUserProvider($this->app->make(RtdbClient::class));
        });

        // The real client IP sits behind the configured proxies, so the checkout
        // and login rate limits count per customer, not per proxy socket
        // (PLAN §4 *Auth & roles* — trusted proxy ranges configured).
        $proxies = config('trustedproxy.proxies');

        if (is_string($proxies) && $proxies !== '') {
            TrustProxies::at($proxies);
        }

        // Named rate limiters, numbers from PLAN §4 *Auth & roles*: checkout
        // 10/min per signed-in user, 30/min per real IP for anonymous traffic,
        // and login 5 per 5 min per forwarded IP + email/sub.
        RateLimiter::for('checkout', function (Request $request): Limit {
            $user = $request->user();

            if ($user instanceof \Illuminate\Contracts\Auth\Authenticatable) {
                $identifier = $user->getAuthIdentifier();

                return Limit::perMinute(10)->by(
                    'checkout:user:' . (is_scalar($identifier) ? (string) $identifier : ''),
                );
            }

            return Limit::perMinute(30)->by('checkout:ip:' . (string) $request->ip());
        });

        // Login: 5 attempts per 5 minutes per forwarded IP + the email/sub when
        // known. The IP is the *forwarded* one (trusted proxies configured
        // above), so customers behind one proxy are counted separately and a
        // bad login loop cannot lock the whole counter out mid-shift.
        RateLimiter::for('login', function (Request $request): Limit {
            $identity = $request->input('email') ?? $request->input('sub');
            $suffix = is_scalar($identity) && (string) $identity !== '' ? ':' . (string) $identity : '';

            return Limit::perMinutes(5, 5)->by('login:ip:' . (string) $request->ip() . $suffix);
        });
    }
}
