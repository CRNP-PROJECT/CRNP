<?php
/**
 * your_profile.php — customer profile view + edit name + upload avatar (controller bootstrap).
 * All logic lives in App\Controllers\User\YourProfileController.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Controllers\User\YourProfileController;
YourProfileController::render();
