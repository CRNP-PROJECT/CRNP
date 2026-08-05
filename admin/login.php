<?php
/**
 * admin/login.php — Administrator sign-in (auth shell, standalone HTML) (controller bootstrap).
 * All logic lives in App\Controllers\Admin\LoginController.
 */
require_once __DIR__ . '/../init.php';
use App\Controllers\Admin\LoginController;
LoginController::render();
