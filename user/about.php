<?php
/**
 * about.php — public About Us page (controller bootstrap).
 * All logic lives in App\Controllers\User\AboutController.
 */
require_once __DIR__ . '/../init.php';
security_headers();
use App\Controllers\User\AboutController;
AboutController::render();
