<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('provisions users/{uid} on a first sign in and signs the customer in', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-10-06 04:12:23', new DateTimeZone('UTC')));

    Socialite::fake('google', SocialiteUser::fake([
        'id' => '110082736',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'avatar' => 'https://example.com/ada.jpg',
    ]));

    $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

    $this->assertAuthenticated();

    expect(rtdb()->get('users/110082736'))->toMatchArray([
        'sub' => '110082736',
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'avatar' => 'https://example.com/ada.jpg',
        'createdAt' => '2026-10-06T04:12:23Z',
    ]);
});

it('reuses the existing users/{uid} node on a returning sign in', function (): void {
    rtdb()->seed('users/110082736', [
        'sub' => '110082736',
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'avatar' => 'https://example.com/ada.jpg',
        'createdAt' => '2026-10-01T00:00:00Z',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => '110082736',
        'name' => 'Ada Lovelace',
    ]));

    $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

    expect(rtdb()->get('users/110082736')['createdAt'])->toBe('2026-10-01T00:00:00Z')
        ->and(array_keys(rtdb()->get('users')))->toHaveCount(1);
});

it('keys the account by Google sub, never by email', function (): void {
    Socialite::fake('google', SocialiteUser::fake(['id' => 'sub-a', 'email' => 'same@example.com']));
    $this->get(route('auth.google.callback'));

    Socialite::fake('google', SocialiteUser::fake(['id' => 'sub-b', 'email' => 'same@example.com']));
    $this->get(route('auth.google.callback'));

    expect(array_keys(rtdb()->get('users')))->toHaveCount(2)
        ->and(rtdb()->get('users/sub-a'))->toMatchArray(['sub' => 'sub-a', 'email' => 'same@example.com'])
        ->and(rtdb()->get('users/sub-b'))->toMatchArray(['sub' => 'sub-b', 'email' => 'same@example.com']);
});

it('lets a visitor browse without signing in', function (): void {
    $this->get('/')->assertOk()->assertSee('Sign in with Google');

    $this->assertGuest();
});

it('prompts the Google button on a gated action while signed out', function (): void {
    Route::middleware('auth')->get('/favorite-action', fn(): string => 'favorited');

    $this->get('/favorite-action')->assertRedirect(route('login'));

    $this->get(route('login'))->assertOk()->assertSee('Sign in with Google');
});

it('returns the customer to the gated action after signing in', function (): void {
    Route::middleware('auth')->get('/favorite-action', fn(): string => 'favorited');

    $this->get('/favorite-action')->assertRedirect(route('login'));

    Socialite::fake('google', SocialiteUser::fake(['id' => '110082736']));
    $this->get(route('auth.google.callback'))->assertRedirect('/favorite-action');

    $this->get('/favorite-action')->assertOk()->assertSee('favorited');
});

it('remembers the session across requests once signed in', function (): void {
    Socialite::fake('google', SocialiteUser::fake(['id' => '110082736', 'name' => 'Ada Lovelace']));

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticated();

    $this->get('/')->assertOk()->assertSee('Ada Lovelace');
});

it('signs the customer out again on sign out', function (): void {
    Socialite::fake('google', SocialiteUser::fake(['id' => '110082736']));
    $this->get(route('auth.google.callback'));
    $this->assertAuthenticated();

    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

it('ships no password, OTP, or email-verification screen', function (): void {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn($route): string => $route->uri())
        ->implode(' ');

    expect($uris)->not->toContain('password')
        ->and($uris)->not->toContain('otp')
        ->and($uris)->not->toContain('verify');

    $this->get(route('login'))->assertOk()
        ->assertDontSee('type="password"', false)
        ->assertDontSee('One-time', false)
        ->assertDontSee('verification code', false);
});

it('starts a stateful PKCE Google redirect', function (): void {
    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirect();

    $location = (string) $response->headers->get('Location');

    expect($location)->toStartWith('https://accounts.google.com/o/oauth2/auth?')
        ->and($location)->toContain('code_challenge=')
        ->and($location)->toContain('code_challenge_method=S256')
        ->and($location)->toContain('state=')
        ->and($location)->toContain('scope=openid');

    $response->assertSessionHas('state');
    $response->assertSessionHas('code_verifier');
});

it('sends the PKCE code verifier back on the callback token exchange', function (): void {
    // The regression: enablePKCE() ran only on the redirect leg, so the callback
    // built a provider with PKCE off, never sent `code_verifier`, and Google
    // answered "Missing code verifier". Drive the real controller callback with
    // a mocked Guzzle transport and assert the token request carries it.
    $history = [];
    $mock = new GuzzleHttp\Handler\MockHandler([
        new GuzzleHttp\Psr7\Response(200, [], json_encode(['access_token' => 'test-token', 'expires_in' => 3600])),
        new GuzzleHttp\Psr7\Response(200, [], json_encode([
            'sub' => '110082736',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ])),
    ]);

    $stack = GuzzleHttp\HandlerStack::create($mock);
    $stack->push(GuzzleHttp\Middleware::history($history));

    config()->set('services.google', [
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
        'redirect' => 'http://localhost:8000/auth/google/callback',
        'guzzle' => ['handler' => $stack],
    ]);

    // The redirect leg stores these; the callback reads them back.
    $state = 'state-from-google';
    $verifier = 'pkce-code-verifier-from-the-redirect';

    $this->withSession(['state' => $state, 'code_verifier' => $verifier])
        ->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => $state]))
        ->assertRedirect(route('home'));

    $tokenRequest = collect($history)->first(
        static fn(array $entry): bool => str_contains((string) $entry['request']->getUri(), 'oauth2/v4/token'),
    );

    expect($tokenRequest)->not->toBeNull();

    parse_str((string) $tokenRequest['request']->getBody(), $body);

    expect($body)->toHaveKey('code_verifier')
        ->and($body['code_verifier'])->toBe($verifier);
});

it('returns to sign-in instead of erroring when the OAuth state is stale', function (): void {
    // A replayed callback (refresh, back button, or an old tab) carries a state
    // the session no longer holds. It must not surface a 500.
    $this->get(route('auth.google.callback', ['code' => 'stale-code', 'state' => 'stale-state']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->get(route('login'))->assertOk()->assertSee('expired');
});

it('returns a cashier to the cashier prompt when the OAuth state is stale', function (): void {
    $this->withSession(['auth.area' => 'cashier'])
        ->get(route('auth.google.callback', ['code' => 'stale-code', 'state' => 'stale-state']))
        ->assertRedirect(route('cashier.login'))
        ->assertSessionHas('status');
});

it('rate-limits the login routes to five per five minutes per forwarded IP', function (): void {
    // Five sign-in attempts pass; the sixth inside the window returns 429.
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->get(route('auth.google.redirect'))
            ->assertRedirect();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->get(route('auth.google.redirect'))
        ->assertStatus(429);
});

it('recovers the login limit inside its five-minute window', function (): void {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->get(route('auth.google.redirect'))
            ->assertRedirect();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
        ->get(route('auth.google.redirect'))
        ->assertStatus(429);

    // The limit decays inside its window: once five minutes elapse the same
    // client is served again, so a bad login loop is self-healing.
    $this->travel(301)->seconds();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
        ->get(route('auth.google.redirect'))
        ->assertRedirect();
});

it('counts the login limit per forwarded IP so one proxy does not lock everyone out', function (): void {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->get(route('auth.google.redirect'))
            ->assertRedirect();
    }

    // A different client behind the same proxy has its own bucket.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
        ->get(route('auth.google.redirect'))
        ->assertRedirect();
});
