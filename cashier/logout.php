<?php
/**
 * logout.php — end the cashier session (controller bootstrap).
 * All logic lives in App\Controllers\Cashier\LogoutController.
 */
require_once __DIR__ . '/../init.php';
use App\Controllers\Cashier\LogoutController;
LogoutController::render();
