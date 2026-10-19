<?php

declare(strict_types=1);

namespace App\Rtdb;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mints and caches the Google OAuth2 access token used to authenticate
 * server-side RTDB REST calls (the service-account 2LO flow).
 *
 * The token lives in the Laravel cache for just under its 1-hour lifetime, so
 * a burst of reads signs and exchanges once. The service-account JSON is read
 * from a path outside the web root and never logged.
 */
final class ServiceAccountTokenProvider
{
    private const SCOPES = 'https://www.googleapis.com/auth/firebase.database '
        . 'https://www.googleapis.com/auth/userinfo.email';

    /**
     * @param  string  $serviceAccountPath  Absolute path to the service-account JSON.
     * @param  string  $tokenUri  Google's OAuth2 token endpoint.
     * @param  int  $ttlSeconds  Cache lifetime, kept below the 3600 s token lifetime.
     * @param  ?string  $serviceAccountJson  Inline service-account JSON; wins over the path when set,
     *                                       so hosts without a writable credential file can supply it.
     */
    public function __construct(
        private readonly string $serviceAccountPath,
        private readonly string $tokenUri,
        private readonly int $ttlSeconds = 3300,
        private readonly ?string $serviceAccountJson = null,
    ) {}

    /**
     * Return a valid access token, minting a new one only when the cache is empty.
     */
    public function token(): string
    {
        $key = 'rtdb_sa_token_' . md5($this->serviceAccountPath . '|' . ($this->serviceAccountJson ?? ''));

        return (string) Cache::remember($key, $this->ttlSeconds, fn(): string => $this->mint());
    }

    private function mint(): string
    {
        $account = $this->readServiceAccount();

        $now = time();
        $assertion = JWT::encode(
            [
                'iss' => $account['client_email'],
                'scope' => self::SCOPES,
                'aud' => $this->tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ],
            $account['private_key'],
            'RS256',
            $account['private_key_id'] ?? null,
        );

        $response = Http::asForm()->post($this->tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ])->throw()->json();

        if (! is_array($response) || ! isset($response['access_token']) || ! is_string($response['access_token'])) {
            throw new RuntimeException('The service-account token exchange returned no access token.');
        }

        return $response['access_token'];
    }

    /**
     * @return array{client_email: string, private_key: string, private_key_id?: string}
     */
    private function readServiceAccount(): array
    {
        $raw = $this->serviceAccountJson;

        if ($raw === null || trim($raw) === '') {
            if (! is_file($this->serviceAccountPath)) {
                throw new RuntimeException('The service-account JSON was not found at the configured path.');
            }

            $raw = file_get_contents($this->serviceAccountPath);

            if ($raw === false) {
                throw new RuntimeException('The service-account JSON could not be read.');
            }
        }

        $account = json_decode($raw, true);

        if (! is_array($account) || ! isset($account['client_email'], $account['private_key'])) {
            throw new RuntimeException('The service-account JSON is missing client_email or private_key.');
        }

        /** @var array{client_email: string, private_key: string, private_key_id?: string} $account */
        return $account;
    }
}
