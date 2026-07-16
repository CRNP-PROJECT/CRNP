<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_ENV = array_merge($_ENV, parse_ini_string(preg_replace('/^\s*#/m', ';', file_get_contents(__DIR__ . '/.env'))));
$databaseURL = $_ENV['FIREBASE_URL'];
?>
