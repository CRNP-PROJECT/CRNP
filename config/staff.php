<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | First admin (one-shot seeder)
    |--------------------------------------------------------------------------
    |
    | `php artisan staff:seed --admin` reads these to create the first staff
    | row, then refuses to run again. No credentials live in the repo — set
    | them in the environment. `role_cache_ttl` is the short window a role
    | read is served from cache; any staff write evicts it immediately.
    |
    */

    'admin' => [
        'name' => env('STAFF_ADMIN_NAME'),
        'email' => env('STAFF_ADMIN_EMAIL'),
    ],

    'role_cache_ttl' => (int) env('STAFF_ROLE_CACHE_TTL', 60),

];
