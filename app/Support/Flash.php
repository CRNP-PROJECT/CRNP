<?php

namespace App\Support;

/**
 * Flash — one-time session flash messages.
 */
class Flash {

    public static function add(string $message, string $type = 'info'): void {
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
    }

    public static function get(): array {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }
}
