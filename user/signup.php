<?php
/**
 * signup.php — customer registration (controller bootstrap).
 * All logic lives in App\Controllers\User\SignupController.
 */
require_once __DIR__ . '/../init.php';
security_headers();
use App\Controllers\User\SignupController;
SignupController::render();
