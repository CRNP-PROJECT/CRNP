<?php

declare(strict_types=1);

// Larastan's bootstrap and analysis exceed PHP's default 128M limit; raise it
// for the PHPStan process (and the workers it forks) so the CI gate runs from
// a cold result cache. Set here rather than in php.ini so the repo is portable.
ini_set('memory_limit', '512M');
