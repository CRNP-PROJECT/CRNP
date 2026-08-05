<?php
/**
 * kitchen/history.php — Read-only archive of completed (done) orders (controller bootstrap).
 * All logic lives in App\Controllers\Kitchen\HistoryController.
 */
require_once __DIR__ . '/../init.php';
require_kitchen();
use App\Controllers\Kitchen\HistoryController;
HistoryController::render();
