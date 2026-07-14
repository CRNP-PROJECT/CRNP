<?php
session_start();
include(__DIR__ . "/config.php");

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: user/login.php");
exit;
