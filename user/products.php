<?php
/**
 * products.php — Customer shop (controller bootstrap).
 * All logic lives in App\Controllers\User\ProductsController.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Controllers\User\ProductsController;
ProductsController::render();
