<?php
/**
 * admin/rent_items.php — Rent inventory CRUD (controller bootstrap).
 * All logic lives in App\Controllers\Admin\RentItemsController.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Controllers\Admin\RentItemsController;
RentItemsController::render();
