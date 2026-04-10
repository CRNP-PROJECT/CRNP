<?php
session_start();
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

// ================= GET DATA =================
$cart_data = $_POST['cart_data'] ?? '';
$customer_name = $_POST['customer_name'] ?? '';
$table_number = $_POST['table_number'] ?? '';
$payment_method = $_POST['payment_method'] ?? 'Cash';

if(!$cart_data || !$customer_name || !$table_number){
    die("Missing order data");
}

$cart = json_decode($cart_data, true);

if(empty($cart)){
    die("Cart is empty");
}

// ================= CALCULATE TOTAL =================
$total = 0;
foreach($cart as $item){
    $total += $item['price'] * $item['qty'];
}

// ================= CREATE ORDER =================
$order_id = uniqid("ORD-");

$order = [
    "order_id" => $order_id,
    "customer_name" => $customer_name,
    "table_number" => $table_number,
    "payment_method" => $payment_method,
    "items" => $cart,
    "total" => $total,
    "status" => "pending",
    "created_at" => date("Y-m-d H:i:s")
];

// ================= SAVE TO FIREBASE =================
$rdb->insert("/orders", $order);

// ================= SEND TO KITCHEN =================
$rdb->insert("/kitchen", $order);

// ================= STORE FOR RECEIPT =================
$_SESSION['last_order'] = $order;

?>

<!-- ================= RECEIPT UI ================= -->
<h2>✅ ORDER SUCCESSFUL</h2>
<h3>🧾 RECEIPT</h3>

<p><b>Order ID:</b> <?= $order['order_id'] ?></p>
<p><b>Customer:</b> <?= $order['customer_name'] ?></p>
<p><b>Table:</b> <?= $order['table_number'] ?></p>
<p><b>Payment:</b> <?= $order['payment_method'] ?></p>
<p><b>Status:</b> <?= $order['status'] ?></p>

<hr>

<h3>Items</h3>

<?php foreach($order['items'] as $item): ?>
    <p>
        <?= $item['name'] ?> x <?= $item['qty'] ?>
        = ₱<?= $item['price'] * $item['qty'] ?>
    </p>
<?php endforeach; ?>

<hr>

<h2>Total: ₱<?= $order['total'] ?></h2>

<br>

<button onclick="window.print()">🖨 Print Receipt</button>

<br><br>

<a href="cashier_index.php">⬅ Back to POS</a>