<?php

namespace App\Support;

/**
 * Security — CSRF tokens, rate limiting, and security headers.
 * Static helper class; the logic previously lived in includes/functions.php.
 */
class Security {

    public static function headers(): void {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://accounts.google.com; frame-src 'self' https://accounts.google.com https://www.google.com https://maps.google.com; connect-src 'self'; object-src 'none'; base-uri 'self'");
    }

    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES) . '">';
    }

    public static function csrfVerify(): void {
        $t = $_POST['csrf_token'] ?? '';
        if (empty($t) || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
            http_response_code(419);
            Flash::add('Security token expired. Please try again.', 'danger');
            Output::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    public static function rateLimit(string $key, int $maxAttempts, int $windowSecs): bool {
        $file = sys_get_temp_dir() . '/rl_' . md5($key) . '.json';
        $now  = time();
        $data = [];
        if (is_file($file)) {
            $data = json_decode((string)file_get_contents($file), true) ?: [];
        }
        $data = array_values(array_filter($data, fn($t) => $t > $now - $windowSecs));
        if (count($data) >= $maxAttempts) {
            return false;
        }
        $data[] = $now;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }
}
