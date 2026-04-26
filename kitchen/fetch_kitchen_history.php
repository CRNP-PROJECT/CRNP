<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

// ================= GET ORDERS =================
$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true);

// ❗ ALWAYS RETURN VALID JSON (prevents vanishing UI)
if(!$orders || !is_array($orders)){
    echo json_encode([
        "walkin" => "",
        "online" => ""
    ]);<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

// ================= GET ORDERS =================
$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true);

// ❗ ALWAYS RETURN VALID JSON
if(!$orders || !is_array($orders)){
    echo json_encode([
        "walkin" => "",
        "online" => ""
    ]);
    exit;
}

// ================= OUTPUT =================
$walkin_html = "";
$online_html = "";

foreach($orders as $id => $order){

    $status = strtolower($order['status'] ?? '');
    $kitchen_status = strtolower($order['kitchen_status'] ?? 'accepted');

    // ❌ skip rejected only
    if($status === 'rejected') continue;

    // normalize
    if(!in_array($kitchen_status, ['accepted','preparing','ready','done'])){
        $kitchen_status = 'accepted';
    }

    // build items
    $items_html = "";

    foreach(($order['products'] ?? []) as $p){
        $items_html .= "• " . htmlspecialchars($p['name'] ?? 'Unknown') .
        " x " . intval($p['qty'] ?? 0) . "<br>";
    }

    // build card
    $html = "
    <div class='card'>
        <strong>" . htmlspecialchars($order['full_name'] ?? 'N/A') . "</strong><br>
        <small>Status: " . strtoupper($kitchen_status) . "</small><br><br>

        $items_html
    </div>
    ";

    // classify
    if(!empty($order['cashier'])){
        $walkin_html .= $html;
    } else {
        $online_html .= $html;
    }
}

// ================= OUTPUT JSON =================
echo json_encode([
    "walkin" => $walkin_html,
    "online" => $online_html
]);
    exit;
}

// ================= OUTPUT HTML =================
$walkin_html = "";
$online_html = "";

foreach($orders as $id => $order){

    $status = strtolower($order['status'] ?? '');
    $kitchen_status = strtolower($order['kitchen_status'] ?? 'accepted');

    // ❌ SKIP ONLY REJECTED ORDERS (SAFE RULE)
    if($status === 'rejected') continue;

    // normalize kitchen status
    if(!in_array($kitchen_status, ['accepted','preparing','ready','done'])){
        $kitchen_status = 'accepted';
    }

    // build items list
    $items_html = "";
    foreach(($order['products'] ?? []) as $p){
        $items_html .= "• " . htmlspecialchars($p['name'] ?? 'Unknown') . 
        " x " . intval($p['qty'] ?? 0) . "<br>";
    }

    // build card
    $html = "
    <div class='card'>
        <strong>" . htmlspecialchars($order['full_name'] ?? 'N/A') . "</strong><br>
        <small>Status: " . strtoupper($kitchen_status) . "</small><br><br>

        $items_html
    </div>
    ";

    // classify orders
    if(!empty($order['cashier'])){
        $walkin_html .= $html;
    } else {
        $online_html .= $html;
    }
}

// ================= SAFE OUTPUT =================
echo json_encode([
    "walkin" => $walkin_html,
    "online" => $online_html
]);