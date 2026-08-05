<?php
/**
 * booking_receipt.php — Printable booking receipt (controller bootstrap).
 * All logic lives in App\Controllers\Cashier\BookingReceiptController.
 */
require_once __DIR__ . '/../init.php';
require_cashier();
use App\Controllers\Cashier\BookingReceiptController;
BookingReceiptController::render();
