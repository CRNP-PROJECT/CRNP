<?php
/**
 * forgot_password.php — Step 1: Enter email, receive OTP to reset password (controller bootstrap).
 * All logic lives in App\Controllers\User\ForgotPasswordController.
 */
require_once __DIR__ . '/../init.php';
security_headers();
use App\Controllers\User\ForgotPasswordController;
ForgotPasswordController::render();
