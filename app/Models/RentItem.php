<?php
namespace App\Models;

use App\Core\Model;

/**
 * RentItem — represents a rental item in /rent_items.
 */
class RentItem extends Model
{
    protected static string $table = 'rent_items';

    protected static array $fillable = [
        'name', 'display_name', 'description', 'price', 'quantity', 'image',
        'status', 'created_at', 'updated_at',
    ];

    /** Count of items with quantity > 0. */
    public static function activeCount(): int
    {
        $n = 0;
        foreach (static::raw() as $r) {
            if (is_array($r) && (int)($r['quantity'] ?? 0) > 0) $n++;
        }
        return $n;
    }

    /** Decrement stock for a rent item. */
    public static function decrementStock(string $itemId, int $qty): void
    {
        $row = static::db()->retrieve('/rent_items/' . $itemId);
        if (!is_array($row) || !isset($row['quantity'])) return;
        $new = max(0, (int)$row['quantity'] - $qty);
        static::db()->update('/rent_items', $itemId, ['quantity' => $new]);
    }

    /** Restore stock for a rent item. */
    public static function restoreStock(string $itemId, int $qty): void
    {
        $row = static::db()->retrieve('/rent_items/' . $itemId);
        $cur = (is_array($row) && isset($row['quantity'])) ? (int)$row['quantity'] : 0;
        static::db()->update('/rent_items', $itemId, ['quantity' => $cur + $qty]);
    }
}
