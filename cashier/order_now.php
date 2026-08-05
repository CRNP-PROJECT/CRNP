<?php
/**
 * order_now.php — POS / walk-in order creation (controller bootstrap).
 * All logic lives in App\Controllers\Cashier\OrderNowController.
 */
require_once __DIR__ . '/../init.php';
require_cashier();
use App\Controllers\Cashier\OrderNowController;
OrderNowController::render();
