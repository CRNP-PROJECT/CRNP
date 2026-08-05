<?php
/**
 * admin/products.php — Product CRUD (menu items) (controller bootstrap).
 * All logic lives in App\Controllers\Admin\ProductsController.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Controllers\Admin\ProductsController;
ProductsController::render();
