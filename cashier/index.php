<?php
/**
 * index.php — Orders management console (controller bootstrap).
 * All logic lives in App\Controllers\Cashier\DashboardController.
 */
require_once __DIR__ . '/../init.php';
require_cashier();
use App\Controllers\Cashier\DashboardController;
DashboardController::render();
