<?php
/**
 * admin/settings.php — CMS-editable business information (controller bootstrap).
 * All logic lives in App\Controllers\Admin\SettingsController.
 */
require_once __DIR__ . '/../init.php';
require_admin();
use App\Controllers\Admin\SettingsController;
SettingsController::render();
