<?php
/**
 * booking_receipt.php — Printable booking receipt for users (controller bootstrap).
 * All logic lives in App\Controllers\User\BookingReceiptController.
 */
require_once __DIR__ . '/../init.php';
require_user();
use App\Controllers\User\BookingReceiptController;
BookingReceiptController::render();
