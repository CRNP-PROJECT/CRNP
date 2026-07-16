<?php
namespace App\Models;

use App\Core\Model;

/**
 * Staff — represents cashier or kitchen staff in /cashiers or /kitchen.
 * Uses a polymorphic path since staff live in different Firebase nodes.
 */
class Staff extends Model
{
    protected static string $table = 'cashiers';

    protected static array $fillable = [
        'name', 'full_name', 'email', 'password_hash', 'created_at',
    ];

    /** Retrieve all staff from a given path. */
    public static function allFrom(string $path): array
    {
        $raw = static::db()->retrieve('/' . $path);
        $rows = \is_array($raw) ? $raw : [];
        uasort($rows, function ($a, $b) {
            $ta = strtotime((string)($a['created_at'] ?? '')) ?: 0;
            $tb = strtotime((string)($b['created_at'] ?? '')) ?: 0;
            return $tb <=> $ta;
        });
        return $rows;
    }

    /** Check if email exists in a given path. */
    public static function emailExists(string $path, string $email): bool
    {
        $all = static::allFrom($path);
        foreach ($all as $c) {
            if (is_array($c) && !empty($c['email']) && strcasecmp((string)$c['email'], $email) === 0) {
                return true;
            }
        }
        return false;
    }

    /** Insert staff into a given path. */
    public static function createIn(string $path, array $data): ?string
    {
        return static::db()->insert('/' . $path, $data);
    }
}
