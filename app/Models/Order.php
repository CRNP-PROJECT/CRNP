<?php
namespace App\Models;

use App\Core\Model;

/**
 * Order — represents a customer order in /orders.
 */
class Order extends Model
{
    protected static string $table = 'orders';

    protected static array $fillable = [
        'user_id', 'user_name', 'user_email',
        'customer_name', 'contact', 'phone',
        'items', 'total',
        'payment_method', 'payment_status', 'receipt', 'gcash_receipt',
        'status', 'table_number', 'num_customers',
        'cash_tendered', 'change',
        'created_at', 'placed_at',
        'accepted_at', 'accepted_by',
        'cancelled_at', 'cancelled_by', 'cancel_note',
        'restored_at', 'restored_by',
        'verified_at', 'verified_by', 'payment_verified',
        'created_by', 'source',
    ];

    /* ---- Convenience statics ---- */

    /** All orders sorted newest-first. Returns raw arrays. */
    public static function allNewest(): array
    {
        $all = static::raw();
        uasort($all, function ($a, $b) {
            $ta = strtotime((string)($a['created_at'] ?? $a['placed_at'] ?? 'now'));
            $tb = strtotime((string)($b['created_at'] ?? $b['placed_at'] ?? 'now'));
            return $tb <=> $ta;
        });
        return $all;
    }

    /** Count orders by status. */
    public static function countByStatus(): array
    {
        $counts = [];
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            $st = (string)($o['status'] ?? 'unknown');
            $counts[$st] = ($counts[$st] ?? 0) + 1;
        }
        return $counts;
    }

    /** Sum totals of paid orders. */
    public static function totalSales(): float
    {
        $sum = 0.0;
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            if ((string)($o['payment_status'] ?? '') === 'paid') {
                $sum += (float)($o['total'] ?? 0);
            }
        }
        return $sum;
    }

    /** Total qty of items in an order (raw array or Model). */
    public static function itemsCount(array|Model $order): int
    {
        $data = $order instanceof Model ? $order->toArray() : $order;
        $n = 0;
        foreach (($data['items'] ?? []) as $info) {
            if (is_array($info)) {
                $n += (int)($info['qty'] ?? 0);
            }
        }
        return $n;
    }

    /** Today's date string. */
    private static function today(): string
    {
        return date('Y-m-d');
    }

    /** Count of today's orders. */
    public static function todayCount(): int
    {
        $today = static::today();
        $n = 0;
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            $d = substr((string)($o['created_at'] ?? ''), 0, 10);
            if ($d === $today) $n++;
        }
        return $n;
    }

    /** Sum of today's paid order totals. */
    public static function todaySales(): float
    {
        $today = static::today();
        $sum = 0.0;
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            $d = substr((string)($o['created_at'] ?? ''), 0, 10);
            if ($d === $today && (string)($o['payment_status'] ?? '') === 'paid') {
                $sum += (float)($o['total'] ?? 0);
            }
        }
        return $sum;
    }

    /** Pending count. */
    public static function pendingCount(): int
    {
        $n = 0;
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            if ((string)($o['status'] ?? '') === 'pending') $n++;
        }
        return $n;
    }

    /** Unpaid count (excludes cancelled). */
    public static function unpaidCount(): int
    {
        $n = 0;
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            $st = (string)($o['status'] ?? '');
            if (($o['payment_status'] ?? '') !== 'paid'
                && $st !== 'cashier_cancelled' && $st !== 'cancelled') {
                $n++;
            }
        }
        return $n;
    }

    /** Last N orders sorted newest-first (raw arrays keyed by Firebase key). */
    public static function recent(int $limit = 8): array
    {
        $all = static::allNewest();
        return array_slice($all, 0, $limit, true);
    }

    /** Best-selling products: name => qty (top N, raw). */
    public static function topProducts(int $limit = 10): array
    {
        $productSales = [];
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            if (in_array(($o['status'] ?? ''), ['cancelled', 'cashier_cancelled'], true)) continue;
            foreach (($o['items'] ?? []) as $pid => $info) {
                if (!is_array($info)) continue;
                $qty = (int)($info['qty'] ?? 0);
                if ($qty <= 0) continue;
                $productSales[$pid] = ($productSales[$pid] ?? 0) + $qty;
            }
        }
        arsort($productSales);
        return array_slice($productSales, 0, $limit, true);
    }

    /** Best-selling by category: category => qty (top N). */
    public static function topCategories(int $limit = 8, array $products = []): array
    {
        $catSales = [];
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            if (in_array(($o['status'] ?? ''), ['cancelled', 'cashier_cancelled'], true)) continue;
            foreach (($o['items'] ?? []) as $pid => $info) {
                if (!is_array($info)) continue;
                $qty = (int)($info['qty'] ?? 0);
                if ($qty <= 0) continue;
                $cat = (string)($products[$pid]['category'] ?? 'Uncategorized');
                $catSales[$cat] = ($catSales[$cat] ?? 0) + $qty;
            }
        }
        arsort($catSales);
        return array_slice($catSales, 0, $limit, true);
    }

    /** Payment method breakdown for paid orders: label => count. */
    public static function paymentMethodBreakdown(): array
    {
        $methods = [];
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            if ((string)($o['payment_status'] ?? '') !== 'paid') continue;
            $pm = (string)($o['payment_method'] ?? 'counter');
            $label = $pm === 'gcash' ? 'GCash' : 'Counter';
            $methods[$label] = ($methods[$label] ?? 0) + 1;
        }
        arsort($methods);
        return $methods;
    }

    /** Peak hours: index 0..23 => count. */
    public static function peakHours(): array
    {
        $hours = array_fill(0, 24, 0);
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            $created = (string)($o['created_at'] ?? '');
            if ($created !== '') {
                $hour = (int)date('G', strtotime($created));
                $hours[$hour]++;
            }
        }
        return $hours;
    }

    /** 7-day sales totals: dateKey => float. */
    public static function last7DaysSales(): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $key = date('Y-m-d', strtotime("-$i days"));
            $days[$key] = 0.0;
        }
        foreach (static::raw() as $o) {
            if (!is_array($o)) continue;
            if ((string)($o['payment_status'] ?? '') !== 'paid') continue;
            $day = substr((string)($o['created_at'] ?? ''), 0, 10);
            if (isset($days[$day])) {
                $days[$day] += (float)($o['total'] ?? 0);
            }
        }
        return $days;
    }

    /** Filter orders by date range [startDate, endDate]. Returns raw arrays. */
    public static function byDateRange(string $startDate, string $endDate): array
    {
        $out = [];
        foreach (static::raw() as $k => $o) {
            if (!is_array($o)) continue;
            $d = substr((string)($o['created_at'] ?? ''), 0, 10);
            if ($d >= $startDate && $d <= $endDate) {
                $out[$k] = $o;
            }
        }
        return $out;
    }

    /** Per-day revenue and count for a date range. */
    public static function dailyStats(string $startDate, string $endDate): array
    {
        $orders = static::byDateRange($startDate, $endDate);
        $stats = [];
        foreach ($orders as $o) {
            if (!is_array($o)) continue;
            $day = substr((string)($o['created_at'] ?? ''), 0, 10);
            if (!isset($stats[$day])) {
                $stats[$day] = ['revenue' => 0.0, 'count' => 0];
            }
            $stats[$day]['count']++;
            if ((string)($o['payment_status'] ?? '') === 'paid') {
                $stats[$day]['revenue'] += (float)($o['total'] ?? 0);
            }
        }
        return $stats;
    }
}
