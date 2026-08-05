<?php

namespace App\Support;

/**
 * Cache — per-request in-memory cache + cross-request file cache with TTL.
 */
class Cache {

    public static function set(string $key, $data, int $ttl = 30): void {
        global $__cache;
        $__cache[$key] = ['data' => $data, 'expires' => time() + $ttl];
    }

    public static function remember(string $key, int $ttl, callable $loader) {
        global $__cache;
        if (isset($__cache[$key]) && $__cache[$key]['expires'] > time()) {
            return $__cache[$key]['data'];
        }
        $data = $loader();
        self::set($key, $data, $ttl);
        return $data;
    }

    public static function fileGet(string $key, int $ttl, callable $loader) {
        $dir = sys_get_temp_dir() . '/crnp_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . md5($key) . '.cache';
        if (is_file($file) && (time() - filemtime($file)) < $ttl) {
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                return unserialize($raw);
            }
        }
        $data = $loader();
        @file_put_contents($file, serialize($data), LOCK_EX);
        return $data;
    }

    public static function fileForget(string $key): void {
        $dir = sys_get_temp_dir() . '/crnp_cache';
        $file = $dir . '/' . md5($key) . '.cache';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function fileClearAll(): void {
        $dir = sys_get_temp_dir() . '/crnp_cache';
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*.cache') ?: [] as $f) {
            @unlink($f);
        }
    }

    public static function businessSettings(): array {
        return self::fileGet('business_settings', 300, function () {
            static $cache = null;
            if ($cache !== null) return $cache;
            $defaults = [
                'business_name'   => BRAND_NAME,
                'tagline'         => BRAND_TAGLINE,
                'address'         => 'Mabolo, Iloilo City Proper, Iloilo City, Philippines',
                'phone'           => '+63 (033) 320-0000',
                'hours'           => 'Tue–Sun · 11:00 AM – 10:00 PM (Closed Mondays)',
                'facebook_url'    => '',
                'instagram_url'   => '',
                'support_email'   => '',
                'hero_title'      => 'Your table is waiting.',
                'hero_subtitle'   => "Order ahead for pickup, reserve a table, or book equipment for your next celebration — all from one account.",
                'about_headline'  => 'Your trusted partner for events and celebrations.',
                'about_body'      => "From everyday meals to special gatherings, we bring quality food and reliable rental equipment to every table we serve in Iloilo City.",
                'about_stat1_num' => '10+', 'about_stat1_lbl' => 'Years Experience',
                'about_stat2_num' => '500+', 'about_stat2_lbl' => 'Events Served',
                'about_stat3_num' => '100%', 'about_stat3_lbl' => 'Satisfaction',
                'gcash_number'    => '0917 000 0000',
                'gcash_qr'        => '',
            ];
            try {
                $db = \getDB();
                $s = $db->retrieve('/settings');
                $cache = is_array($s) ? array_merge($defaults, $s) : $defaults;
            } catch (\Throwable $e) {
                $cache = $defaults;
            }
            return $cache;
        });
    }
}
