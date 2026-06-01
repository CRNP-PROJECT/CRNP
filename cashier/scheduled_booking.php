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

    $rdb->update("/bookings", $bookingId, $update);

    header("Location: scheduled_booking.php?date=" . ($_GET['date'] ?? date('Y-m-d')));
    exit;
}

/* ================= DATE ================= */

$date = $_GET['date'] ?? date("Y-m-d");

$year  = date('Y', strtotime($date));
$month = date('m', strtotime($date));

$firstDay = date('Y-m-01', strtotime($date));
$daysInMonth = date('t', strtotime($firstDay));
$startDay = date('w', strtotime($firstDay));

/* ================= BOOKINGS ================= */

$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

/* ================= CALENDAR ================= */

$calendar = [];

foreach($bookings as $id => $b){

    if(!is_array($b)) continue;

    $status = strtolower($b['status'] ?? '');

    if(!in_array($status, ['accepted','done'])) continue;

    $appointment = $b['appointment_time'] ?? '';
    if(!$appointment) continue;

    $d = date("Y-m-d", strtotime($appointment));

    if(!isset($calendar[$d])){
        $calendar[$d] = ['bookings' => 0];
    }

    $calendar[$d]['bookings']++;
}

/* ================= SELECTED DAY ================= */

$scheduled = [];

foreach($bookings as $id => $b){

    if(!is_array($b)) continue;

    $status = strtolower($b['status'] ?? '');
    if(!in_array($status, ['accepted','done'])) continue;

    $appointment = $b['appointment_time'] ?? '';
    if(!$appointment) continue;

    $d = date("Y-m-d", strtotime($appointment));

    if($d !== $date) continue;

    $scheduled[] = [
        "id" => $id,
        "name" => $b['full_name'] ?? 'Unknown',
        "address" => $b['address'] ?? '',
        "contact" => $b['contact_number'] ?? '',
        "total" => $b['booking_total'] ?? 0,
        "time" => date("h:i A", strtotime($appointment)),
        "status" => $status,
        "items" => $b['items'] ?? [],
        "delivered_at" => $b['delivered_at'] ?? '',
        "delivered_by" => $b['delivered_by'] ?? '',
        "delivery_note" => $b['delivery_note'] ?? ''
    ];
}

function peso($n){
    return "₱" . number_format($n,2);
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Booking Calendar</title>

<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../style.css">
</head>

<body class="schedule-booking-page">

<!-- ================= NAVBAR FIXED ================= -->
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

<!-- ================= CALENDAR ================= -->

<div class="daily_report_card">

    <div class="daily_report_calendar_header">
        <h5><?= date('F Y', strtotime($date)) ?></h5>
    </div>

    <div class="daily_report_calendar">

        <?php for($i=0;$i<7;$i++): ?>
            <div class="daily_report_calendar_dayname">
                <?= ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$i] ?>
            </div>
        <?php endfor; ?>

        <?php for($i=0;$i<$startDay;$i++): ?>
            <div></div>
        <?php endfor; ?>

        <?php for($d=1;$d<=$daysInMonth;$d++):

            $full = date('Y-m-d', strtotime("$year-$month-$d"));

            $b = $calendar[$full]['bookings'] ?? 0;

            $class = "";
            if($b > 0) $class = "daily_report_bookings_only";
        ?>

        <a href="?date=<?= $full ?>"
           class="daily_report_day_box <?= $class ?> <?= $full==$date?'daily_report_active_day':'' ?>">

            <div class="daily_report_day_number"><?= $d ?></div>

            <div class="daily_report_muted">
                Bookings: <?= $b ?>
            </div>

        </a>

        <?php endfor; ?>

    </div>
</div>

<!-- ================= SELECTED DAY ================= -->

<h5 class="mt-4">
    Appointments on <?= $date ?>
</h5>

<div class="schedule-booking-grid">

<?php if(empty($scheduled)): ?>
    <div class="alert alert-warning">
        No bookings for this date
    </div>
<?php endif; ?>

<?php foreach($scheduled as $s): ?>

<div class="schedule-booking-card-box">

    <h5><?= htmlspecialchars($s['name']) ?></h5>

    <p><b>Time:</b> <?= $s['time'] ?></p>
    <p><b>Contact:</b> <?= $s['contact'] ?></p>
    <p><b>Address:</b> <?= $s['address'] ?></p>

    <hr>

    <p><b>Total:</b> <?= peso($s['total']) ?></p>

<p><b>Status:</b> <?= strtoupper($s['status']) ?></p>

<!-- ================= DELIVERY INFO ================= -->

<?php if(!empty($s['delivered_at'])): ?>

    <div class="alert alert-success">
        ✔ Delivered<br>
        <?= date("M d, Y h:i A", strtotime($s['delivered_at'])) ?><br>
        By: <?= htmlspecialchars($s['delivered_by']) ?>

        <?php if(!empty($s['delivery_note'])): ?>
            <br><br>
            <b>Notes:</b> <?= htmlspecialchars($s['delivery_note']) ?>
        <?php endif; ?>
    </div>

<?php else: ?>

    <!-- ================= DELIVERY FORM ================= -->

    <form method="POST" action="scheduled_booking.php?date=<?= $date ?>">

        <input type="hidden" name="booking_id" value="<?= $s['id'] ?>">

        <label><b>Delivery Notes</b></label>

        <textarea
            name="delivery_note"
            class="form-control mb-2"
            required
            placeholder="Enter delivery remarks..."
        ></textarea>

        <button
            type="submit"
            name="deliver_booking"
            class="btn btn-success w-100"
            onclick="return confirm('Mark as delivered?')"
        >
            Mark as Delivered
        </button>

    </form>

<?php endif; ?>
</div>

<?php endforeach; ?>

</div>

</div>

</body>
</html>