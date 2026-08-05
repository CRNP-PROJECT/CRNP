<?php
/**
 * admin/history.php — full order + booking history (controller bootstrap).
 * All logic lives in App\Controllers\Admin\HistoryController.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Controllers\Admin\HistoryController;
HistoryController::render();
