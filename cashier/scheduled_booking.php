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
<link href="https://cdn.jsdelivr.com/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

 
</head>

<body class="schedule-booking-page">

<header class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo" alt="Logo">
    </div>

    <div class="navbar-right">

        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="create_order.php">Create Orders</a></li>
            <li><a href="view_orders.php">Orders</a></li>
            <li><a href="cashier_orderHistory.php">History</a></li>
            <li><a href="scheduled_booking.php" class="active">Schedule Booking</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>
    </div>

</header>

<div class="container mt-4 schedule-booking-container">

<div class="schedule-booking-header">
    <h4>Booking Calendar</h4>
    <small>Click a date to view appointments</small>
</div>

<!-- CALENDAR SELECTOR -->
<div class="schedule-booking-calendar-box">
    <form method="GET" class="schedule-booking-form">

        <label class="schedule-booking-label">
            <b>Select Date:</b>
        </label>

        <input 
            type="date" 
            name="date" 
            value="<?= $selectedDate ?>" 
            class="form-control schedule-booking-date-input mt-2"
            onchange="this.form.submit()"
        >

    </form>
</div>

<!-- SELECTED DATE -->
<h5 class="schedule-booking-selected-date">
    Appointments on <?= $selectedDate ?>
</h5>

<div class="schedule-booking-grid">

<?php if(empty($scheduled)): ?>

    <div class="alert alert-warning schedule-booking-empty">
        No bookings for this date
    </div>

<?php endif; ?>

<?php foreach($scheduled as $s): ?>

<div class="schedule-booking-card-box">

    <div class="schedule-booking-card-top">
        <h5 class="schedule-booking-client-name">
            <?= htmlspecialchars($s['name']) ?>
        </h5>
    </div>

    <div class="schedule-booking-info-group">

        <p class="schedule-booking-info-text">
            <b>Appointment Time:</b> <?= $s['time'] ?>
        </p>

        <p class="schedule-booking-info-text">
            <b>Address:</b> <?= $s['address'] ?>
        </p>

        <p class="schedule-booking-info-text">
            <b>Contact:</b> <?= $s['contact'] ?>
        </p>

    </div>

    <hr class="schedule-booking-divider">

    <p class="schedule-booking-item-title">
        <b>Items:</b>
    </p>

    <div class="schedule-booking-items">
        <?= $s['items'] ?: "No items" ?>
    </div>

    <hr class="schedule-booking-divider">

    <div class="schedule-booking-payment-section">

        <p class="schedule-booking-payment-text">
            <b>Total:</b> ₱<?= number_format($s['total'],2) ?>
        </p>

        <p class="schedule-booking-payment-text">
            <b>Method:</b> <?= $s['payment_method'] ?>
        </p>

        <p class="schedule-booking-payment-text <?= $s['payment_color'] ?>">
            <b>Status:</b> <?= $s['payment_status'] ?>
        </p>

    </div>

</div>

<?php endforeach; ?>

</div>

</div>

</body>
</html>