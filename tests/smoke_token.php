<?php
/**
 * smoke_token.php — offline checks for firebaseRDB service-account auth.
 *
 * Run: docker run --rm -v "$PWD":/app -w /app php:8.2-cli php tests/smoke_token.php
 * (or `php tests/smoke_token.php` on any PHP 8 with curl+openssl).
 * Never hits the network: the live token exchange needs real Google credentials.
 */
declare(strict_types=1);

require __DIR__ . '/../firebaseRDB.php';

$fails = 0;
function check(string $name, bool $ok): void {
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name\n";
    if (!$ok) $fails++;
}

$db       = new firebaseRDB('https://example-default-rtdb.firebaseio.com');
$refToken = new ReflectionMethod('firebaseRDB', 'accessToken');
$refToken->setAccessible(true);
$refB64   = new ReflectionMethod('firebaseRDB', '_b64url');
$refB64->setAccessible(true);

// 1. Unset env -> unauthenticated fallback.
putenv('FIREBASE_CREDENTIALS');
check('returns null when FIREBASE_CREDENTIALS unset', $refToken->invoke($db) === null);

// 2. Malformed JSON -> null, no crash.
putenv('FIREBASE_CREDENTIALS=not-json{{');
check('returns null on malformed JSON', $refToken->invoke($db) === null);

// 3. Well-shaped JSON but junk key -> null at signing.
$sa = ['client_email' => 'junk@junk.iam.gserviceaccount.com',
       'private_key'  => "-----BEGIN PRIVATE KEY-----\nAAAA\n-----END PRIVATE KEY-----\n"];
putenv('FIREBASE_CREDENTIALS=' . json_encode($sa));
check('returns null when private key unparseable', $refToken->invoke($db) === null);

// 4. base64url vectors.
check('b64url standard JWT header', $refB64->invoke($db, '{"alg":"RS256"}') === 'eyJhbGciOiJSUzI1NiJ9');
check('b64url maps +/ to -_', $refB64->invoke($db, "\xfb\xff\xbf") === '-_-_');
// 5. Valid on-disk cache short-circuits before any network call.
$email     = 'cached@cache.iam.gserviceaccount.com';
$cacheFile = sys_get_temp_dir() . '/crnp_fb_token_' . md5($email) . '.json';
file_put_contents($cacheFile, json_encode(['token' => 'fake-token', 'exp' => time() + 3000, 'email' => $email]));
putenv('FIREBASE_CREDENTIALS=' . json_encode(['client_email' => $email, 'private_key' => 'whatever']));
check('serves cached token without network', $refToken->invoke($db) === 'fake-token');
unlink($cacheFile);

// 6. Runtime supports the RS256 sign/verify roundtrip we depend on.
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_sign('payload', $sig, $key, OPENSSL_ALGO_SHA256);
$pub = openssl_pkey_get_details($key)['key'];
check('RS256 sign/verify roundtrip', openssl_verify('payload', $sig, $pub, OPENSSL_ALGO_SHA256) === 1);

exit($fails === 0 ? 0 : 1);
