<?php
/**
 * login.php — Cashier Console sign-in (controller bootstrap).
 * All logic lives in App\Controllers\Cashier\LoginController.
 */
require_once __DIR__ . '/../init.php';
use App\Controllers\Cashier\LoginController;
LoginController::render();
