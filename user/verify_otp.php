<?php
/**
 * verify_otp.php — confirms a 6-digit OTP for a pending customer signup (controller bootstrap).
 * All logic lives in App\Controllers\User\VerifyOtpController.
 */
require_once __DIR__ . '/../init.php';
security_headers();
use App\Controllers\User\VerifyOtpController;
VerifyOtpController::render();
