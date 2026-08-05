<?php
/**
 * checkout.php — Customer order checkout (controller bootstrap).
 * All logic lives in App\Controllers\User\CheckoutController.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Controllers\User\CheckoutController;
CheckoutController::render();
