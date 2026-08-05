<?php
/**
 * google_auth.php — Google Identity Services callback (controller bootstrap).
 * All logic lives in App\Controllers\User\GoogleAuthController.
 */
require_once __DIR__ . '/../init.php';
security_headers();
use App\Controllers\User\GoogleAuthController;
GoogleAuthController::render();
