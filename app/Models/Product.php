<?php
namespace App\Models;

use App\Core\Model;

/**
 * Product — represents a product in /products.
 */
class Product extends Model
{
    protected static string $table = 'products';

    protected static array $fillable = [
        'name', 'description', 'price', 'stock', 'category',
        'image', 'status', 'created_at', 'updated_at',
    ];

    /** Get all products as a plain array keyed by Firebase key (for lookups). */
    public static function allKeyed(): array
    {
        return static::raw();
    }

    /** Count products with stock <= $threshold. */
    public static function lowStockCount(int $threshold = 5): int
    {
        $n = 0;
        foreach (static::raw() as $p) {
            if (is_array($p) && (int)($p['stock'] ?? 0) <= $threshold) $n++;
        }
        return $n;
    }

    /** Decrement stock for a product. */
    public static function decrementStock(string $productId, int $qty): void
    {
        $row = static::db()->retrieve('/products/' . $productId);
        if (!is_array($row) || !isset($row['stock'])) return;
        $new = max(0, (int)$row['stock'] - $qty);
        static::db()->update('/products', $productId, ['stock' => $new]);
    }

    /** Restore stock for a product. */
    public static function restoreStock(string $productId, int $qty): void
    {
        $row = static::db()->retrieve('/products/' . $productId);
        $cur = (is_array($row) && isset($row['stock'])) ? (int)$row['stock'] : 0;
        static::db()->update('/products', $productId, ['stock' => $cur + $qty]);
    }
}
