<?php
/**
 * logout.php — ends the customer session and returns to the login screen (controller bootstrap).
 * All logic lives in App\Controllers\User\LogoutController.
 */
require_once __DIR__ . '/../init.php';
use App\Controllers\User\LogoutController;
LogoutController::render();
