<?php
/**
 * admin/logout.php — End the admin session (controller bootstrap).
 * All logic lives in App\Controllers\Admin\LogoutController.
 */
require_once __DIR__ . '/../init.php';
use App\Controllers\Admin\LogoutController;
LogoutController::render();
