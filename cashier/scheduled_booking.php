<?php
session_start();

include(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$selectedDate = $_GET['date'] ?? date("Y-m-d");

$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

$scheduled = [];

foreach($bookings as $id => $b){
    if(!is_array($b)) continue;
    if(($b['status'] ?? '') !== 'accepted') continue;

    $datetime = $b['appointment_time'] ?? '';
    if(!$datetime) continue;

    $date = date("Y-m-d", strtotime($datetime));
    $time = date("h:i A", strtotime($datetime));

    /* FILTER BY CLICKED DATE */
    if($date !== $selectedDate) continue;

    /* ITEMS */
    $itemsText = "";
    if(!empty($b['items']) && is_array($b['items'])){
        foreach($b['items'] as $item){
            $name = $item['name'] ?? 'Item';
            $qty  = $item['qty'] ?? 1;
            $price = $item['price'] ?? 0;

            $itemsText .= "• {$name} (x{$qty}) - ₱{$price}<br>";
        }
    }

    /* PAYMENT */
    $paymentMethod = $b['payment_method'] ?? '';

    if($paymentMethod === "counter"){
        $paymentStatus = "PAID (Counter)";
        $paymentColor = "text-success";
    }
    else if(($b['payment_status'] ?? '') === "no_payment_required"){
        $paymentStatus = "No Payment Required";
        $paymentColor = "text-warning";
    }
    else{
        $paymentStatus = $b['payment_status'] ?? 'Pending';
        $paymentColor = "text-danger";
    }

    $scheduled[] = [
        "name" => $b['full_name'] ?? 'Unknown',
        "address" => $b['address'] ?? '',
        "contact" => $b['contact_number'] ?? '',
        "email" => $b['user_email'] ?? '',
        "total" => $b['booking_total'] ?? 0,
        "payment_method" => $paymentMethod,
        "payment_status" => $paymentStatus,
        "payment_color" => $paymentColor,
        "items" => $itemsText,
        "date" => $date,
        "time" => $time
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Booking Calendar</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f6f9;}

.header{
    background:#0d6efd;
    color:#fff;
    padding:15px;
    border-radius:12px;
    margin-bottom:15px;
}

.calendar-box{
    background:white;
    padding:15px;
    border-radius:12px;
    box-shadow:0 4px 10px rgba(0,0,0,0.08);
    margin-bottom:20px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
    gap:15px;
}

.card-box{
    background:white;
    padding:15px;
    border-radius:16px;
    box-shadow:0 4px 10px rgba(0,0,0,0.08);
}

.items{
    font-size:13px;
    background:#f8f9fa;
    padding:8px;
    border-radius:10px;
}
</style>
</head>

<body>
    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="view_bookings.php" class="active">Bookings</a></li>
            <li><a href="booking_history.php">History</a></li>
            <li><a href="booking_payment.php">Payment Status</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>
    </div>

<div class="container mt-4">

<div class="header">
    <h4> Booking Calendar</h4>
    <small>Click a date to view appointments</small>
</div>

<!-- CALENDAR SELECTOR -->
<div class="calendar-box">
    <form method="GET">
        <label><b>Select Date:</b></label>
        <input type="date" name="date" value="<?= $selectedDate ?>" class="form-control mt-2" onchange="this.form.submit()">
    </form>
</div>

<!-- SELECTED DATE -->
<h5> Appointments on <?= $selectedDate ?></h5>

<div class="grid">

<?php if(empty($scheduled)): ?>
    <div class="alert alert-warning">No bookings for this date</div>
<?php endif; ?>

<?php foreach($scheduled as $s): ?>

<div class="card-box">

    <h5><?= htmlspecialchars($s['name']) ?></h5>

    <p><b>Appointment Time:</b> <?= $s['time'] ?></p>

    <p><b> Address:</b> <?= $s['address'] ?></p>
    <p><b> Contact:</b> <?= $s['contact'] ?></p>

    <hr>

    <p><b>Items:</b></p>
    <div class="items"><?= $s['items'] ?: "No items" ?></div>

    <hr>

    <p><b> Total:</b> ₱<?= number_format($s['total'],2) ?></p>
    <p><b> Method:</b> <?= $s['payment_method'] ?></p>

    <p class="<?= $s['payment_color'] ?>">
        <b>Status:</b> <?= $s['payment_status'] ?>
    </p>

</div>

<?php endforeach; ?>

</div>

</div>

</body>
</html>