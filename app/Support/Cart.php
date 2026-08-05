<?php

namespace App\Support;

/**
 * Cart — session-based shopping cart.
 */
class Cart {

    public static function get(): array {
        return $_SESSION['cart'] ?? [];
    }

    public static function set(array $cart): void {
        $_SESSION['cart'] = $cart;
    }

    public static function count(): int {
        $n = 0;
        foreach (self::get() as $item) {
            $n += (int)($item['qty'] ?? 0);
        }
        return $n;
    }

    public static function total(): float {
        $t = 0.0;
        foreach (self::get() as $item) {
            $t += (float)($item['price'] ?? 0) * (int)($item['qty'] ?? 0);
        }
        return $t;
    }
}
