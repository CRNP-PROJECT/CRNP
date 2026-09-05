<?php
/**
 * firebaseRDB — thin cURL wrapper over the Firebase Realtime Database REST API.
 *
 *   retrieve($path, $queryKey, $queryType, $queryVal)  -> array (GET)
 *   insert($table, $data)                              -> new key (POST)
 *   update($table, $id, $data)                         -> patched data (PATCH)
 *   delete($table, $id)                                -> true (DELETE)
 *
 * insert/update/delete throw on {"error": ...} responses.
 * On cURL failure the error is logged and retrieve() returns [].
 *
 * Auth: when FIREBASE_CREDENTIALS holds a service-account JSON, every request
 * is signed with a cached OAuth2 access token (see accessToken()). Without it,
 * requests go out unauthenticated — pair with locked-down RTDB rules in prod.
 */
class firebaseRDB {
    public const EQUAL = 'EQUAL';
    public const LIKE  = 'LIKE';

    /** @var string */
    public $url;

    /** @var string */
    private $lastError = '';

    public function __construct(string $url) {
        $this->url = rtrim($url, '/');
    }

    /**
     * GET a node. When $queryKey is supplied a server-side query is attempted;
     * returns an associative array (decoded). On cURL failure returns [].
     *
     * Advanced options ($options map):
     *   limitToLast => int
     *   limitToFirst => int
     *   startAt => string|int
     *   endAt => string|int
     */
    public function retrieve(string $path, ?string $queryKey = null, string $queryType = self::EQUAL, $queryVal = null, array $options = []) {
        $url = $this->url . '/' . ltrim($path, '/') . '.json';
        $qs = [];
        if ($queryKey !== null && $queryVal !== null) {
            $val = (string)$queryVal;
            if ($queryType === self::LIKE) {
                $qs[] = 'orderBy="' . rawurlencode($queryKey) . '"';
                $qs[] = 'startAt="' . rawurlencode($val) . '"';
                $qs[] = 'endAt="' . rawurlencode($val) . '\uf8ff"';
            } else {
                $qs[] = 'orderBy="' . rawurlencode($queryKey) . '"';
                $qs[] = 'equalTo="' . rawurlencode($val) . '"';
            }
        }
        if (!empty($options['limitToLast']))  $qs[] = 'limitToLast=' . (int)$options['limitToLast'];
        if (!empty($options['limitToFirst'])) $qs[] = 'limitToFirst=' . (int)$options['limitToFirst'];
        if (isset($options['startAt']))       $qs[] = 'startAt="' . rawurlencode((string)$options['startAt']) . '"';
        if (isset($options['endAt']))         $qs[] = 'endAt="' . rawurlencode((string)$options['endAt']) . '"';
        if ($qs !== []) {
            $url .= '?' . implode('&', $qs);
        }
        $resp = $this->_exec($url, 'GET');
        if ($resp === null) {
            return [];
        }
        $data = json_decode($resp, true);
        return $data === null ? [] : $data;
    }

    /** POST — creates a new auto-key. Returns the new Firebase push key. */
    public function insert(string $table, array $data) {
        $url  = $this->url . '/' . ltrim($table, '/') . '.json';
        $resp = $this->_exec($url, 'POST', json_encode($data, JSON_UNESCAPED_UNICODE));
        if ($resp === null) {
            throw new RuntimeException('Firebase insert failed (cURL).');
        }
        $arr = json_decode($resp, true);
        $this->_guardError($arr);
        return $arr['name'] ?? null;
    }

    /** PATCH — partial update of a child node. */
    public function update(string $table, string $id, array $data) {
        $url  = $this->url . '/' . ltrim($table, '/') . '/' . rawurlencode($id) . '.json';
        $resp = $this->_exec($url, 'PATCH', json_encode($data, JSON_UNESCAPED_UNICODE));
        if ($resp === null) {
            throw new RuntimeException('Firebase update failed (cURL).');
        }
        $arr = json_decode($resp, true);
        $this->_guardError($arr);
        return $arr;
    }

    /** PATCH a whole node directly (e.g. /settings) without a child id. */
    public function updateNode(string $path, array $data) {
        $url  = $this->url . '/' . ltrim($path, '/') . '.json';
        $resp = $this->_exec($url, 'PATCH', json_encode($data, JSON_UNESCAPED_UNICODE));
        if ($resp === null) {
            throw new RuntimeException('Firebase updateNode failed (cURL).');
        }
        $arr = json_decode($resp, true);
        $this->_guardError($arr);
        return $arr;
    }

    /** DELETE — remove a child node. */
    public function delete(string $table, string $id): bool {
        $url  = $this->url . '/' . ltrim($table, '/') . '/' . rawurlencode($id) . '.json';
        $resp = $this->_exec($url, 'DELETE');
        if ($resp === null) {
            throw new RuntimeException('Firebase delete failed (cURL).');
        }
        $arr = json_decode($resp, true);
        $this->_guardError($arr);
        return true;
    }

    /**
     * Short-lived Google OAuth2 access token minted from the FIREBASE_CREDENTIALS
     * service-account JSON via an RS256 JWT bearer grant. Returns null when the
     * variable is unset/invalid so callers fall back to unauthenticated REST
     * (local dev against open rules). Valid tokens are memoized per-request and
     * cached on disk until a minute before expiry.
     */
    private function accessToken(): ?string
    {
        static $memo = null;
        if ($memo !== null && $memo['exp'] > time() + 60) {
            return $memo['token'];
        }

        static $warnedUnset = false;
        $raw = getenv('FIREBASE_CREDENTIALS');
        if ($raw === false || trim($raw) === '') {
            if (!$warnedUnset) {
                $warnedUnset = true;
                error_log('[firebaseRDB] FIREBASE_CREDENTIALS is not set; RTDB requests go out unsigned.');
            }
            return null;
        }
        $sa = json_decode($raw, true);
        if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            error_log('[firebaseRDB] FIREBASE_CREDENTIALS is set but is not a valid service-account JSON.');
            return null;
        }

        $cacheFile = sys_get_temp_dir() . '/crnp_fb_token_' . md5($sa['client_email']) . '.json';
        if (is_readable($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)
                && ($cached['email'] ?? '') === $sa['client_email']
                && ($cached['exp'] ?? 0) > time() + 60) {
                $memo = $cached;
                return $cached['token'];
            }
        }

        $now   = time();
        $input = $this->_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
               . '.' . $this->_b64url(json_encode([
                     'iss'   => $sa['client_email'],
                     'scope' => 'https://www.googleapis.com/auth/firebase.database https://www.googleapis.com/auth/userinfo.email',
                     'aud'   => 'https://oauth2.googleapis.com/token',
                     'iat'   => $now,
                     'exp'   => $now + 3600,
                 ]));
        $pkey = openssl_pkey_get_private($sa['private_key']);
        if ($pkey === false || !openssl_sign($input, $sig, $pkey, OPENSSL_ALGO_SHA256)) {
            error_log('[firebaseRDB] Failed to sign the service-account JWT.');
            return null;
        }
        $jwt = $input . '.' . $this->_b64url($sig);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('[firebaseRDB] Token exchange cURL error: ' . curl_error($ch));
            return null;
        }
        $tok = json_decode((string) $resp, true);
        if (empty($tok['access_token'])) {
            error_log('[firebaseRDB] Token exchange failed: ' . substr((string) $resp, 0, 300));
            return null;
        }

        $entry = [
            'token' => $tok['access_token'],
            'exp'   => time() + (int) ($tok['expires_in'] ?? 3600),
            'email' => $sa['client_email'],
        ];
        if (file_put_contents($cacheFile, json_encode($entry)) === false) {
            error_log('[firebaseRDB] Could not write token cache file; will re-authenticate next request.');
        }
        $memo = $entry;
        return $entry['token'];
    }

    /** URL-safe base64 without padding. */
    private function _b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function _exec(string $url, string $method, ?string $body = null): ?string {
        $token = $this->accessToken();
        if ($token !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'access_token=' . rawurlencode($token);
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
            ]);
        }
        $resp = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->lastError = curl_error($ch);
            // Never let the bearer token reach persistent logs via the URL.
            $safeUrl = preg_replace('/([?&])access_token=[^&]+/', '$1access_token=<redacted>', $url);
            error_log('[firebaseRDB] cURL error: ' . $this->lastError . ' | ' . $safeUrl);
            return null;
        }
        return $resp === false ? null : $resp;
    }

    /** @param mixed $arr decoded JSON */
    private function _guardError($arr): void {
        if (is_array($arr) && isset($arr['error'])) {
            $msg = is_string($arr['error']) ? $arr['error'] : json_encode($arr['error']);
            throw new RuntimeException('Firebase error: ' . $msg);
        }
    }
}
