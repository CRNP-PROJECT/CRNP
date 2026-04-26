<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

$user_email = $_SESSION['email'] ?? '';
$username = $_SESSION['username'] ?? 'User';

if(!$user_email){
    header("Location: login.php");
    exit;
}

/* ================= FILTER INPUTS ================= */
$type_filter = $_GET['type'] ?? 'orders'; // orders | bookings
$status_filter = $_GET['status'] ?? 'all';

/* ================= FETCH DATA ================= */
$orders = json_decode($rdb->retrieve("/orders"), true) ?? [];
$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

/* ================= FILTER FUNCTION ================= */
function filterData($data, $email, $status_filter){

    $result = [];

    foreach($data as $id => $item){

        $item_email = $item['email']
            ?? $item['user_email']
            ?? '';

        if($item_email !== $email) continue;

        $status = strtolower($item['status'] ?? 'pending');

        if($status_filter !== 'all' && $status !== $status_filter){
            continue;
        }

        $result[$id] = $item;
    }

    return $result;
}

/* ================= APPLY FILTER ================= */
$user_orders = filterData($orders, $user_email, $status_filter);
$user_bookings = filterData($bookings, $user_email, $status_filter);

$data = ($type_filter === 'bookings') ? $user_bookings : $user_orders;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your Orders</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<style>
.filter-bar{
    margin:20px 0;
}

.filter-bar a{
    padding:8px 12px;
    margin-right:8px;
    text-decoration:none;
    border-radius:6px;
    background:#eee;
    color:#333;
}

.filter-bar a.active{
    background:#333;
    color:#fff;
}
</style>
</head>

<body>

<!-- NAV -->
<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>
</nav>

<div class="your-orders-container">

<h1>Your <?= ucfirst($type_filter) ?></h1>

<!-- TYPE FILTER -->
<div class="filter-bar">
    <a href="?type=orders&status=all" class="<?= ($type_filter=='orders')?'active':'' ?>">Orders</a>
    <a href="?type=bookings&status=all" class="<?= ($type_filter=='bookings')?'active':'' ?>">Bookings</a>
</div>

<!-- STATUS FILTER -->
<div class="filter-bar">
    <a href="?type=<?= $type_filter ?>&status=all">All</a>
    <a href="?type=<?= $type_filter ?>&status=pending">Pending</a>
    <a href="?type=<?= $type_filter ?>&status=accepted">Accepted</a>
    <a href="?type=<?= $type_filter ?>&status=rejected">Rejected</a>
    <a href="?type=<?= $type_filter ?>&status=done">Done</a>
</div>

<!-- LIST -->
<div class="your-orders-grid">

<?php if(empty($data)): ?>
    <p>No records found.</p>
<?php endif; ?>

<?php foreach($data as $id => $item): ?>

<?php $status = strtolower($item['status'] ?? 'pending'); ?>

<div class="your-orders-card">

    <div class="your-orders-header">
        <strong><?= htmlspecialchars($item['full_name'] ?? 'User') ?></strong>

        <span class="your-orders-badge your-orders-<?= $status ?>">
            <?= strtoupper($status) ?>
        </span>
    </div>

    <p>₱<?= number_format($item['total'] ?? 0, 2) ?></p>

    <?php
    $products = $item['products'] ?? $item['items'] ?? [];
    ?>

    <?php if(!empty($products)): ?>
        <ul>
            <?php foreach($products as $p): ?>
                <li><?= htmlspecialchars($p['name'] ?? '') ?> x <?= intval($p['qty'] ?? 1) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>

</div>

</body>
</html>