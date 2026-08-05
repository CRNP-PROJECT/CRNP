<?php
/**
 * kitchen/index.php — Kitchen Display (controller bootstrap).
 * All logic lives in App\Controllers\Kitchen\OrdersController.
 */
require_once __DIR__ . '/../init.php';
require_kitchen();
use App\Controllers\Kitchen\OrdersController;
OrdersController::render();
