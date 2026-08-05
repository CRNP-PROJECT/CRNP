<?php
/**
 * admin/reports.php — Sales report with calendar date picker (controller bootstrap).
 * All logic lives in App\Controllers\Admin\ReportsController.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Controllers\Admin\ReportsController;
ReportsController::render();
