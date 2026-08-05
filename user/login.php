<?php
/**
 * login.php — customer sign-in (email + password, or Google Identity Services) (controller bootstrap).
 * All logic lives in App\Controllers\User\LoginController.
 */
require_once __DIR__ . '/../init.php';
security_headers();
use App\Controllers\User\LoginController;
LoginController::render();
