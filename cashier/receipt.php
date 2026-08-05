<?php
/**
 * receipt.php — Printable POS receipt for walk-in orders (controller bootstrap).
 * All logic lives in App\Controllers\Cashier\ReceiptController.
 */
require_once __DIR__ . '/../init.php';
require_cashier();
use App\Controllers\Cashier\ReceiptController;
ReceiptController::render();
