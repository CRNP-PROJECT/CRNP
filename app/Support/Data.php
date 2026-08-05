<?php

namespace App\Support;

/**
 * Data — request input and PHP-side filtering of Firebase rows.
 */
class Data {

    public static function rows($data): array {
        return is_array($data) ? $data : [];
    }

    public static function filterBy(array $rows, string $key, $val): array {
        $out = [];
        foreach ($rows as $id => $row) {
            if (is_array($row) && array_key_exists($key, $row) && strcasecmp((string)$row[$key], (string)$val) === 0) {
                $out[$id] = $row;
            }
        }
        return $out;
    }

    public static function filterLike(array $rows, string $key, $val): array {
        $out = [];
        $v = strtolower((string)$val);
        foreach ($rows as $id => $row) {
            if (is_array($row) && array_key_exists($key, $row) && strpos(strtolower((string)$row[$key]), $v) !== false) {
                $out[$id] = $row;
            }
        }
        return $out;
    }

    public static function post(string $key, $default = '') {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    public static function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
