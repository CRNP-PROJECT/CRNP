<?php
/**
 * history.php — read-only archive (controller bootstrap).
 * All logic lives in App\Controllers\Cashier\HistoryController.
 */
require_once __DIR__ . '/../init.php';
require_cashier();
use App\Controllers\Cashier\HistoryController;
HistoryController::render();
