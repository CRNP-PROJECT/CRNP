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

    private function _exec(string $url, string $method, ?string $body = null): ?string {
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
            error_log('[firebaseRDB] cURL error: ' . $this->lastError . ' | ' . $url);
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
