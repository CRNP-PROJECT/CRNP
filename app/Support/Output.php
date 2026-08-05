<?php

namespace App\Support;

/**
 * Output — escaping, redirects, formatting, and small value helpers.
 */
class Output {

    public static function escape($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    public static function redirect(string $url): void {
        header('Location: ' . $url);
        exit;
    }

    public static function now(): string {
        return date('Y-m-d H:i:s');
    }

    public static function money($n): string {
        return "\u{20B1}" . number_format((float)$n, 2);
    }

    public static function genOtp(): string {
        try {
            return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            return str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }
    }

    public static function itemsHtml($items): string {
        if (!is_array($items) || empty($items)) {
            return '<span class="muted">No items</span>';
        }
        $parts = [];
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $name = (string)($it['name'] ?? 'Item');
            $qty  = (int)($it['qty'] ?? $it['quantity'] ?? 1);
            $parts[] = self::escape($name) . ' <span class="muted">&times;' . $qty . '</span>';
        }
        return $parts ? implode(', ', $parts) : '<span class="muted">No items</span>';
    }
}
