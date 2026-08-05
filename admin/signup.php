<?php
/**
 * admin/signup.php — Bootstrap the first administrator account (controller bootstrap).
 * All logic lives in App\Controllers\Admin\SignupController.
 */
require_once __DIR__ . '/../init.php';
use App\Controllers\Admin\SignupController;
SignupController::render();
