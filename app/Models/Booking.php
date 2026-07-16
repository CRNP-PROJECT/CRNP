<?php
namespace App\Models;

use App\Core\Model;

/**
 * Booking — represents a booking/reservation in /bookings.
 */
class Booking extends Model
{
    protected static string $table = 'bookings';

    protected static array $fillable = [
        'user_id', 'user_name', 'user_email',
        'items', 'total',
        'status', 'payment_status', 'payment_method',
        'appointment_time', 'created_at',
        'cancelled_at', 'cancelled_by', 'cancel_note',
        'accepted_at', 'accepted_by',
        'returned_at', 'returned_by',
    ];

    /** Today's count. */
    public static function todayCount(): int
    {
        $today = date('Y-m-d');
        $n = 0;
        foreach (static::raw() as $b) {
            if (!is_array($b)) continue;
            $d = substr((string)($b['created_at'] ?? ''), 0, 10);
            if ($d === $today) $n++;
        }
        return $n;
    }

    /** Total booking count. */
    public static function totalCount(): int
    {
        return count(static::raw());
    }

    /** Bookings by status: label => count. */
    public static function statusBreakdown(): array
    {
        $statuses = [];
        foreach (static::raw() as $b) {
            if (!is_array($b)) continue;
            $st = (string)($b['status'] ?? 'unknown');
            [$label] = \booking_status_label($st);
            $statuses[$label] = ($statuses[$label] ?? 0) + 1;
        }
        arsort($statuses);
        return $statuses;
    }

    /** Best-selling rent items from bookings: name => qty (top N). */
    public static function topRentItems(int $limit = 10, array $rentItems = []): array
    {
        $sales = [];
        foreach (static::raw() as $b) {
            if (!is_array($b)) continue;
            if (in_array(($b['status'] ?? ''), ['cancelled', 'rejected'], true)) continue;
            foreach (($b['items'] ?? []) as $rid => $info) {
                if (!is_array($info)) continue;
                $qty = (int)($info['qty'] ?? 0);
                if ($qty <= 0) continue;
                $name = (string)($rentItems[$rid]['name'] ?? $info['name'] ?? 'Item');
                $sales[$name] = ($sales[$name] ?? 0) + $qty;
            }
        }
        arsort($sales);
        return array_slice($sales, 0, $limit, true);
    }

    /** Last N bookings sorted newest-first (raw arrays). */
    public static function recent(int $limit = 5): array
    {
        $all = static::raw();
        uasort($all, function ($a, $b) {
            $ta = strtotime((string)($a['created_at'] ?? '')) ?: 0;
            $tb = strtotime((string)($b['created_at'] ?? '')) ?: 0;
            return $tb <=> $ta;
        });
        return array_slice($all, 0, $limit, true);
    }

    /** Filter bookings by date range [startDate, endDate]. */
    public static function byDateRange(string $startDate, string $endDate): array
    {
        $out = [];
        foreach (static::raw() as $k => $b) {
            if (!is_array($b)) continue;
            $d = substr((string)($b['created_at'] ?? ''), 0, 10);
            if ($d >= $startDate && $d <= $endDate) {
                $out[$k] = $b;
            }
        }
        return $out;
    }

    /** Per-day revenue and count for a date range. */
    public static function dailyStats(string $startDate, string $endDate): array
    {
        $bookings = static::byDateRange($startDate, $endDate);
        $stats = [];
        foreach ($bookings as $b) {
            if (!is_array($b)) continue;
            $day = substr((string)($b['created_at'] ?? ''), 0, 10);
            if (!isset($stats[$day])) {
                $stats[$day] = ['revenue' => 0.0, 'count' => 0];
            }
            $stats[$day]['count']++;
            if ((string)($b['payment_status'] ?? '') === 'paid') {
                $stats[$day]['revenue'] += (float)($b['total'] ?? 0);
            }
        }
        return $stats;
    }
}
