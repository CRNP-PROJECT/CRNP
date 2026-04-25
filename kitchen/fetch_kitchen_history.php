<?php
session_start();
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true) ?? [];

$walkin_html = "";
$online_html = "";

foreach($orders as $id => $order){

    if(($order['status'] ?? '') !== 'accepted') continue;

    $kitchen_status = $order['kitchen_status'] ?? 'accepted';

    if($kitchen_status === 'done') continue;

    if(!in_array($kitchen_status, ['accepted','preparing','ready'])){
        $kitchen_status = 'accepted';
    }

    $html = "
    <div class='card'>
        <strong>".htmlspecialchars($order['full_name'] ?? '')."</strong><br>
        <small>Status: ".strtoupper($kitchen_status)."</small><br><br>";

    foreach(($order['products'] ?? []) as $p){
        $html .= "• ".htmlspecialchars($p['name'])." x ".intval($p['qty'])."<br>";
    }

    $html .= "</div>";

    if(!empty($order['cashier'])){
        $walkin_html .= $html;
    } else {
        $online_html .= $html;
    }
}

echo json_encode([
    "walkin" => $walkin_html,
    "online" => $online_html
]);