<?php
include(__DIR__ . "/config.php");

// Destroy all session data
$_SESSION = [];
session_unset();
session_destroy();

// Redirect to login page
header("Location: user/login.php");
exit;
?>