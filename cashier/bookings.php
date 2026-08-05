<?php
/**
 * bookings.php — Rental bookings queue (controller bootstrap).
 * All logic lives in App\Controllers\Cashier\BookingsController.
 */
require_once __DIR__ . '/../init.php';
require_cashier();
use App\Controllers\Cashier\BookingsController;
BookingsController::render();
