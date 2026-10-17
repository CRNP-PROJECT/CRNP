<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The checkout and login rate limits key anonymous traffic on the *real*
    | client IP. Behind nginx or Cloudflare every customer shares one socket
    | address, so without trusted proxies they collapse into a single bucket and
    | one bad loop locks the whole counter out mid-shift (PLAN §4 *Auth &
    | roles*). Set this to the proxy ranges (comma-separated) in production;
    | `*` trusts the calling address, which is what the test suite uses.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
