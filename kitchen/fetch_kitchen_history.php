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

    if(!is_array($order)) continue;

    $status = strtolower($order['status'] ?? '');
    if($status === 'rejected') continue;

    $kitchen_status = strtolower($order['kitchen_status'] ?? 'accepted');

    // still hide completed orders from LIVE dashboard
    if($kitchen_status === 'done') continue;

    $items_html = "";

    foreach(($order['products'] ?? []) as $p){
        $items_html .= "• " . htmlspecialchars($p['name']) .
        " x " . intval($p['qty']) . "<br>";
    }

    // ================= BUTTON FLOW =================
    $buttons = "";

    if($kitchen_status === "accepted"){
        $buttons = "
        <form method='POST' action='kitchen_process.php'>
            <input type='hidden' name='action' value='update_status'>
            <input type='hidden' name='order_id' value='$id'>
            <button name='status' value='preparing'>Preparing</button>
        </form>";
    }

    elseif($kitchen_status === "preparing"){
        $buttons = "
        <form method='POST' action='kitchen_process.php'>
            <input type='hidden' name='action' value='update_status'>
            <input type='hidden' name='order_id' value='$id'>
            <button name='status' value='ready'>Ready</button>
        </form>";
    }

    elseif($kitchen_status === "ready"){
        $buttons = "
        <form method='POST' action='kitchen_process.php'>
            <input type='hidden' name='action' value='update_status'>
            <input type='hidden' name='order_id' value='$id'>
            <button name='status' value='done'>Done</button>
        </form>";
    }

    $html = "
    <div class='card'>
        <strong>" . htmlspecialchars($order['full_name'] ?? 'N/A') . "</strong><br>
        <small>Status: " . strtoupper($kitchen_status) . "</small><br><br>

        $items_html
        <br>
        $buttons
    </div>";

    // ================= SPLIT =================
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
?>