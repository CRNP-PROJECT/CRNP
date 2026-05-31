<?php
session_start();

include(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

/* ================= DELIVER BOOKING ================= */

if(isset($_POST['deliver_booking'])){

    $bookingId = $_POST['booking_id'];
    $deliveryNote = trim($_POST['delivery_note'] ?? '');

    if($deliveryNote == ''){
        die("Delivery note is required.");
    }

    $update = [
        "status" => "done",
        "delivered_at" => date("Y-m-d H:i:s"),
        "delivered_by" => $_SESSION['cashier_email'],
        "delivery_note" => $deliveryNote
    ];

    $rdb->update(
        "/bookings",
        $bookingId,
        $update
    );

    header(
        "Location: scheduled_booking.php?date=" .
        ($_GET['date'] ?? date('Y-m-d'))
    );
    exit;
}

$selectedDate = $_GET['date'] ?? date("Y-m-d");

$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

$scheduled = [];

foreach($bookings as $id => $b){

    if(!is_array($b)) continue;

    // ONLY ACCEPTED BOOKINGS
    if(($b['status'] ?? '') !== 'accepted') continue;

    $datetime = $b['appointment_time'] ?? '';
    if(!$datetime) continue;

    $date = date("Y-m-d", strtotime($datetime));
    $time = date("h:i A", strtotime($datetime));
    $returnRaw = $b['return_time'] ?? '';
    $returnDate = $returnRaw ? date("Y-m-d", strtotime($returnRaw)) : '';
    $returnTime = $returnRaw ? date("h:i A", strtotime($returnRaw)) : '';

    if($date !== $selectedDate) continue;

    /* ITEMS */
    $itemsText = "";

    if(!empty($b['items']) && is_array($b['items'])){

        foreach($b['items'] as $item){

            $name = $item['name'] ?? 'Item';
            $qty  = $item['qty'] ?? 1;
            $price = $item['price'] ?? 0;

            $itemsText .=
                "• {$name} (x{$qty}) - ₱{$price}<br>";
        }
    }

    /* PAYMENT */
    $paymentMethod = $b['payment_method'] ?? '';

    if($paymentMethod === "counter"){
        $paymentStatus = "PAID (Counter)";
        $paymentColor = "text-success";

    } else if(
        ($b['payment_status'] ?? '') ===
        "no_payment_required"
    ){
        $paymentStatus = "No Payment Required";
        $paymentColor = "text-warning";

    } else {
        $paymentStatus =
            $b['payment_status'] ?? 'Pending';
        $paymentColor = "text-danger";
    }

    $scheduled[] = [

        "id" => $id,
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
        "time" => $time,

        /* DELIVERY TRACKING */
        "delivered_at" => $b['delivered_at'] ?? '',
        "delivered_by" => $b['delivered_by'] ?? '',
        "delivery_note" => $b['delivery_note'] ?? '',

        "return_date" => $returnDate,
        "return_time" => $returnTime,
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
</head>

<body class="schedule-booking-page">

<header class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="cashier_index.php">Dashboard</a></li>
            <li><a href="create_order.php">Create Orders</a></li>
            <li><a href="view_orders.php">Orders</a></li>
            <li><a href="view_bookings.php">Bookings</a></li>
            <li><a href="scheduled_booking.php" class="active">Schedule Booking</a></li>
            <li><a href="cashier_orderHistory.php">History</a></li>
            <li><a href="cashier_logout.php">Logout</a></li>
        </ul>
    </div>

</header>

<div class="container mt-4 schedule-booking-container">

<div class="schedule-booking-header">
    <h4>Booking Calendar</h4>
    <small>Click a date to view appointments</small>
</div>

<div class="schedule-booking-calendar-box">

    <form method="GET">
        <label><b>Select Date:</b></label>

        <input
            type="date"
            name="date"
            value="<?= $selectedDate ?>"
            class="form-control mt-2"
            onchange="this.form.submit()"
        >
    </form>

</div>

<h5 class="schedule-booking-selected-date">
    Appointments on <?= $selectedDate ?>
</h5>

<div class="schedule-booking-grid">

<?php if(empty($scheduled)): ?>
    <div class="alert alert-warning">
        No bookings for this date
    </div>
<?php endif; ?>

<?php foreach($scheduled as $s): ?>

<div class="schedule-booking-card-box">

    <div class="schedule-booking-card-top">
        <h5><?= htmlspecialchars($s['name']) ?></h5>
    </div>

    <p><b>Appointment:</b> <?= $s['time'] ?></p>
    <?php if(!empty($s['return_date'])): ?>
    <p>
        <b>Return:</b>
        <?= date("M d, Y", strtotime($s['return_date'])) ?>
        - <?= $s['return_time'] ?>
    </p>
<?php endif; ?>

    <p><b>Address:</b> <?= $s['address'] ?></p>
    <p><b>Contact:</b> <?= $s['contact'] ?></p>

    <hr>

    <b>Items:</b>
    <div><?= $s['items'] ?: "No items" ?></div>

    <hr>

    <p>
        <b>Total:</b>
        ₱<?= number_format($s['total'],2) ?>
    </p>

    <p>
        <b>Method:</b>
        <?= $s['payment_method'] ?>
    </p>

    <p class="<?= $s['payment_color'] ?>">
        <b>Status:</b>
        <?= $s['payment_status'] ?>
    </p>

    <!-- DELIVERY INFO -->
    <?php if(!empty($s['delivered_at'])): ?>

        <hr>

        <div class="alert alert-success p-2">

            <b>Delivered At:</b><br>
            <?= date(
                "M d, Y h:i A",
                strtotime($s['delivered_at'])
            ) ?><br>

            <small>

                By:
                <?= htmlspecialchars($s['delivered_by']) ?>

                <br>

                <?php if(!empty($s['delivery_note'])): ?>
                    Notes:
                    <?= htmlspecialchars($s['delivery_note']) ?>
                <?php endif; ?>

            </small>

        </div>

    <?php endif; ?>

    <!-- DELIVERY FORM -->
    <?php if(empty($s['delivered_at'])): ?>

    <form method="POST">

        <input
            type="hidden"
            name="booking_id"
            value="<?= $s['id'] ?>"
        >

        <label class="mt-2">
            <b>Delivery Notes</b>
        </label>

        <textarea
            name="delivery_note"
            class="form-control mb-2"
            required
            placeholder="Enter who delivered / remarks / notes"
        ></textarea>

        <button
            type="submit"
            name="deliver_booking"
            class="btn btn-success w-100"
            onclick="return confirm('Confirm delivery?')"
        >
            Delivered
        </button>

    </form>

    <?php else: ?>

        <div class="text-center text-success fw-bold">
            ✔ Already Delivered
        </div>

    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>

</div>

</body>
</html>