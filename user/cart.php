<?php
/**
 * cart.php — Customer shopping cart (controller bootstrap).
 * All logic lives in App\Controllers\User\CartController.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Controllers\User\CartController;
CartController::render();
