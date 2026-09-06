<?php
namespace App\Core;

/**
 * Model — lightweight ActiveRecord-style base for Firebase RTDB entities.
 *
 * Each subclass sets $table (the Firebase path) and optionally $fillable.
 * Static helpers wrap getDB() so page files stay thin:
 *
 *   Order::all();
 *   Product::find($id);
 *   Booking::where('status', 'pending');
 *   $order = new Order($data); $order->save();
 */
abstract class Model
{
    /** Firebase path node (e.g. 'orders', 'products'). Subclasses MUST set this. */
    protected static string $table = '';

    /** Fields that mass-assignment will accept (empty = allow all). */
    protected static array $fillable = [];

    /** Raw attribute bag (keyed by Firebase field name). */
    protected array $attributes = [];

    /** Firebase push key (set after insert). */
    protected ?string $key = null;

    /** Per-request cache for raw() to avoid duplicate cURL calls. */
    private static array $rawCache = [];

    /** Per-request cache for derived sorted/filtered data. */
    protected static array $derivedCache = [];

    /* ------------------------------------------------------------------ */
    /*  Construction                                                       */
    /* ------------------------------------------------------------------ */

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /* ------------------------------------------------------------------ */
    /*  DB accessor (shared firebaseRDB instance via global getDB())        */
    /* ------------------------------------------------------------------ */

    protected static function db(): \firebaseRDB
    {
        return \getDB();
    }

    /* ------------------------------------------------------------------ */
    /*  Finders                                                            */
    /* ------------------------------------------------------------------ */

    /** Return every row under the table node, keyed by Firebase push key. */
    public static function all(): array
    {
        return static::hydrateMany(static::raw());
    }

    /** Find a single record by its Firebase key. Returns null when missing. */
    public static function find(string $key): ?static
    {
        $row = static::db()->retrieve('/' . static::$table . '/' . $key);
        if (!is_array($row)) return null;
        $model = new static($row);
        $model->key = $key;
        return $model;
    }

    /** Retrieve raw rows (arrays) — for pages that still iterate raw data. */
    public static function raw(): array
    {
        $table = static::$table;
        if (!isset(self::$rawCache[$table])) {
            self::$rawCache[$table] = cache_file_get('model_raw_' . $table, 30, function () use ($table) {
                $raw = static::db()->retrieve('/' . $table);
                return \is_array($raw) ? $raw : [];
            });
        }
        return self::$rawCache[$table];
    }

    /** PHP-side filter (avoids indexOn). Returns raw arrays keyed by key. */
    public static function where(string $field, mixed $value): array
    {
        $all = static::raw();
        return \filter_by($all, $field, $value);
    }

    /** Return the latest $limit rows by a date field, newest first (raw arrays). */
    public static function recentLimited(string $dateField, int $limit = 50): array
    {
        $table = static::$table;
        $key = $table . ':recent:' . $dateField . ':' . $limit;
        if (!isset(self::$derivedCache[$key])) {
            $all = static::raw();
            $total = count($all);
            if ($total <= $limit) {
                uasort($all, function ($a, $b) use ($dateField) {
                    $ta = strtotime((string)($a[$dateField] ?? ''));
                    $tb = strtotime((string)($b[$dateField] ?? ''));
                    if ($ta === false && $tb === false) return 0;
                    if ($ta === false) return 1;
                    if ($tb === false) return -1;
                    return $tb <=> $ta;
                });
                self::$derivedCache[$key] = $all;
            } else {
                $timestamps = [];
                foreach ($all as $k => $v) {
                    $t = strtotime((string)($v[$dateField] ?? ''));
                    $timestamps[$k] = $t === false ? 0 : $t;
                }
                arsort($timestamps);
                $keys = array_slice(array_keys($timestamps), 0, $limit, true);
                $out = [];
                foreach ($keys as $k) {
                    $out[$k] = $all[$k];
                }
                self::$derivedCache[$key] = $out;
            }
        }
        return self::$derivedCache[$key];
    }

    /** Paginate raw rows: returns ['data' => ..., 'page' => ..., 'perPage' => ..., 'total' => ...]. */
    public static function paginate(int $page = 1, int $perPage = 50): array
    {
        $all = static::raw();
        $total = count($all);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $data = array_slice($all, $offset, $perPage, true);
        return [
            'data'    => $data,
            'page'    => $page,
            'perPage' => $perPage,
            'total'   => $total,
            'pages'   => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Persist                                                            */
    /* ------------------------------------------------------------------ */

    /** Insert into Firebase. Sets $this->key and returns it. */
    public function save(): string
    {
        $data = $this->toFillableArray();
        if ($this->key !== null) {
            static::db()->update('/' . static::$table, $this->key, $data);
            static::clearRawCache();
            return $this->key;
        }
        $newKey = static::db()->insert('/' . static::$table, $data);
        $this->key = $newKey;
        static::clearRawCache();
        return $newKey;
    }

    /** Partial update (PATCH) of specific fields. */
    public function update(array $attrs): void
    {
        foreach ($attrs as $k => $v) {
            $this->attributes[$k] = $v;
        }
        if ($this->key !== null) {
            static::db()->update('/' . static::$table, $this->key, $attrs);
            static::clearRawCache();
        }
    }

    /** Delete this record from Firebase. */
    public function delete(): void
    {
        if ($this->key !== null) {
            static::db()->delete('/' . static::$table, $this->key);
            $this->key = null;
            static::clearRawCache();
        }
    }

    /** Bust the per-request + file cache for this table. */
    public static function clearRawCache(): void
    {
        $table = static::$table;
        unset(self::$rawCache[$table]);
        self::$derivedCache = [];
        cache_file_forget('model_raw_' . $table);
    }

    /* ------------------------------------------------------------------ */
    /*  Attribute access                                                   */
    /* ------------------------------------------------------------------ */

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /** Allow $order->status syntax (read-only). */
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    /** Allow isset($order->status). */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /* ------------------------------------------------------------------ */
    /*  Array / JSON export                                                */
    /* ------------------------------------------------------------------ */

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /* ------------------------------------------------------------------ */
    /*  Internal helpers                                                   */
    /* ------------------------------------------------------------------ */

    protected function fill(array $attrs): void
    {
        $fillable = static::$fillable;
        foreach ($attrs as $k => $v) {
            if (empty($fillable) || in_array($k, $fillable, true)) {
                $this->attributes[$k] = $v;
            }
        }
    }

    /** Return only fillable fields (or all if $fillable is empty). */
    protected function toFillableArray(): array
    {
        $fillable = static::$fillable;
        if (empty($fillable)) return $this->attributes;
        return array_intersect_key($this->attributes, array_flip($fillable));
    }

    /** Hydrate a single raw array into a Model instance with key set. */
    protected static function hydrateOne(string $key, array $row): static
    {
        $model = new static($row);
        $model->key = $key;
        return $model;
    }

    /** Hydrate many raw rows into Model instances keyed by Firebase key. */
    protected static function hydrateMany(array $rows): array
    {
        $out = [];
        foreach ($rows as $k => $v) {
            if (is_array($v)) {
                $out[$k] = static::hydrateOne($k, $v);
            }
        }
        return $out;
    }
}
