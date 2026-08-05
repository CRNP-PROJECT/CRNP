<?php
/**
 * kitchen/login.php — Kitchen staff sign-in (controller bootstrap).
 * All logic lives in App\Controllers\Kitchen\LoginController.
 */
require_once __DIR__ . '/../init.php';
security_headers();
use App\Controllers\Kitchen\LoginController;
LoginController::render();
