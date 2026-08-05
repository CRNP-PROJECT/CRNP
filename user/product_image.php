<?php
/**
 * product_image.php — serves product/rent-item images from Firebase b64 data (controller bootstrap).
 * All logic lives in App\Controllers\User\ProductImageController.
 */
require_once __DIR__ . '/../init.php';
use App\Controllers\User\ProductImageController;
ProductImageController::render();
