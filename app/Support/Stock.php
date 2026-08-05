<?php

namespace App\Support;

/**
 * Stock — rent-item stock operations (decrement on booking, restore on cancel).
 * Uses the global \firebaseRDB wrapper for the raw updates.
 */
class Stock {

    public static function decrementRent(\firebaseRDB $db, string $itemId, int $qty, ?int $currentStock = null): void {
        if ($currentStock === null) {
            $row = $db->retrieve('/rent_items/' . $itemId);
            if (!is_array($row) || !isset($row['quantity'])) {
                return;
            }
            $currentStock = (int)$row['quantity'];
        }
        $new = max(0, $currentStock - $qty);
        $db->update('/rent_items', $itemId, ['quantity' => $new]);
        Cache::fileForget('model_raw_rent_items');
    }

    public static function restoreRent(\firebaseRDB $db, string $itemId, int $qty, ?int $currentStock = null): void {
        if ($currentStock === null) {
            $row = $db->retrieve('/rent_items/' . $itemId);
            $currentStock = (is_array($row) && isset($row['quantity'])) ? (int)$row['quantity'] : 0;
        }
        $db->update('/rent_items', $itemId, ['quantity' => $currentStock + $qty]);
        Cache::fileForget('model_raw_rent_items');
    }
}
