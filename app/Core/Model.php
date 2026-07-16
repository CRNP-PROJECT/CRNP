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
        $raw = static::db()->retrieve('/' . static::$table);
        $rows = \is_array($raw) ? $raw : [];
        return static::hydrateMany($rows);
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
        $raw = static::db()->retrieve('/' . static::$table);
        return \is_array($raw) ? $raw : [];
    }

    /** PHP-side filter (avoids indexOn). Returns raw arrays keyed by key. */
    public static function where(string $field, mixed $value): array
    {
        $all = static::raw();
        return \filter_by($all, $field, $value);
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
            return $this->key;
        }
        $newKey = static::db()->insert('/' . static::$table, $data);
        $this->key = $newKey;
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
        }
    }

    /** Delete this record from Firebase. */
    public function delete(): void
    {
        if ($this->key !== null) {
            static::db()->delete('/' . static::$table, $this->key);
            $this->key = null;
        }
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
