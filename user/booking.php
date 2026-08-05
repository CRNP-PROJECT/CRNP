<?php
/**
 * booking.php — Customer rental booking (controller bootstrap).
 * All logic lives in App\Controllers\User\BookingController.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Controllers\User\BookingController;
BookingController::render();
