<?php
/**
 * admin/index.php — Dashboard: KPI strip, 7-day sales chart, recent activity (controller bootstrap).
 * All logic lives in App\Controllers\Admin\DashboardController.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Controllers\Admin\DashboardController;
DashboardController::render();
