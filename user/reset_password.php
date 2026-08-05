<?php
/**
 * reset_password.php — Step 2: Enter OTP + new password to reset (controller bootstrap).
 * All logic lives in App\Controllers\User\ResetPasswordController.
 */
require_once __DIR__ . '/../init.php';
security_headers();
use App\Controllers\User\ResetPasswordController;
ResetPasswordController::render();
