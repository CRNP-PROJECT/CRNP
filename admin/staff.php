<?php
/**
 * admin/staff.php — Manage cashier & kitchen accounts (controller bootstrap).
 * All logic lives in App\Controllers\Admin\StaffController.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Controllers\Admin\StaffController;
StaffController::render();
