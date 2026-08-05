<?php
/**
 * manual_booking.php — create a walk-in rental booking (controller bootstrap).
 * All logic lives in App\Controllers\Cashier\ManualBookingController.
 */
require_once __DIR__ . '/../init.php';
require_cashier();
use App\Controllers\Cashier\ManualBookingController;
ManualBookingController::render();
