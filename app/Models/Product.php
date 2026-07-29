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
        'name', 'description', 'price', 'category',
        'image', 'status', 'created_at', 'updated_at',
    ];

    /** Get all products as a plain array keyed by Firebase key (for lookups). */
    public static function allKeyed(): array
    {
        return static::raw();
    }
}
