<?php
/**
 * admin/bookings.php — Rent reservations overview (controller bootstrap).
 * All logic lives in App\Controllers\Admin\BookingsController.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Controllers\Admin\BookingsController;
BookingsController::render();
