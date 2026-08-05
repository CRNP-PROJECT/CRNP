<?php
/**
 * kitchen/logout.php — end the kitchen session and return to the sign-in (controller bootstrap).
 * All logic lives in App\Controllers\Kitchen\LogoutController.
 */
require_once __DIR__ . '/../init.php';
use App\Controllers\Kitchen\LogoutController;
LogoutController::render();
