<?php
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

$history_raw = $rdb->retrieve("/kitchen_history");
$history = json_decode($history_raw, true) ?? [];

header('Content-Type: application/json');
echo json_encode($history);