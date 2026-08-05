<?php
/**
 * your_orders.php — Customer order & booking history (controller bootstrap).
 * All logic lives in App\Controllers\User\YourOrdersController.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Controllers\User\YourOrdersController;
YourOrdersController::render();
