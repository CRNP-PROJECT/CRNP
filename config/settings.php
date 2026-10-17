<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Business settings
    |--------------------------------------------------------------------------
    |
    | All wall-clock evaluation happens in the diner's timezone (the server
    | clock is UTC), so opening hours, scheduled closures, and the close sweep
    | agree. `cache_ttl` bounds a settings read no write has touched; every
    | settings write evicts it. The shared catalog cache key and TTL live in
    | `App\Cache\CatalogCache` (#21) — a settings write (hours, force-close,
    | closures, banner) calls its `forget()` so an emergency close propagates on
    | the next request.
    |
    */

    'timezone' => env('BUSINESS_TIMEZONE', 'Asia/Manila'),

    'cache_ttl' => (int) env('SETTINGS_CACHE_TTL', 120),

    /*
    | The longest edge the official GCash QR is scaled to before storage. The
    | shared compressor (`App\Support\Image\ImageCompressor`) does the work;
    | this only feeds the admin screen's copy so the number has one home.
    */

    'qr_max_edge' => 800,

    /*
    | The diner's public Facebook page, linked from the About page's Connect
    | With Us button. Kept in config so the profile URL has one home rather than
    | being hardcoded in the view.
    */

    'facebook_url' => env('FACEBOOK_URL', 'https://www.facebook.com/cratesnplatesdiner/'),

];
