<?php
session_start();
session_destroy();
header("Location: kitchen_login.php");
exit;
?>