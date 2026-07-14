<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

/* ================= MONTH + DATE ================= */

$month = $_GET['month'] ?? date("m");
$year  = $_GET['year'] ?? date("Y");

$selectedDate = $_GET['date'] ?? date("Y-m-d");

/* ================= CALENDAR HELPERS ================= */

$firstDay    = date("Y-m-01", strtotime("$year-$month-01"));
$daysInMonth = date("t", strtotime($firstDay));
$startDay    = date("w", strtotime($firstDay));

/* ================= FETCH BOOKINGS ================= */

$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

/* ================= CALENDAR DATA ================= */

$calendar = [];

foreach($bookings as $id => $b){

    if(!is_array($b)) continue;

    $status = strtolower($b['status'] ?? '');
    $returnTime = $b['return_time'] ?? '';

    if(empty($returnTime)) continue;

    // 🔥 USE RETURN DATE (NOT APPOINTMENT)
    $date = date("Y-m-d", strtotime($returnTime));

    if(!isset($calendar[$date])){
        $calendar[$date] = [
            "returned" => 0,
            "pending" => 0,
            "due_soon" => 0
        ];
    }

    // count status
    if($status === "returned"){
        $calendar[$date]["returned"]++;
    } else {
        $calendar[$date]["pending"]++;
    }

    // 🔥 DUE SOON (5 DAYS RULE)
    $daysLeft = (strtotime($returnTime) - time()) / 86400;

    if($daysLeft <= 5 && $daysLeft >= 0){
        $calendar[$date]["due_soon"]++;
    }

    /* ================= SELECTED DAY DATA ================= */

    if($date === $selectedDate){

        $returnBookings[] = [
            "id" => $id,
            "name" => $b['full_name'] ?? 'Unknown',
            "contact" => $b['contact_number'] ?? '',
            "address" => $b['address'] ?? '',
            "total" => $b['booking_total'] ?? 0,
            "return_time" => $b['return_time'] ?? '',
            "status" => $status,
            "delivery_note" => $b['delivery_note'] ?? '',
            "returned_at" => $b['returned_at'] ?? '',
            "appointment_time" => $b['appointment_time'] ?? '',
        ];
    }
}

/* ================= MONTH NAV ================= */

$prev = date("Y-m", strtotime("$year-$month-01 -1 month"));
$next = date("Y-m", strtotime("$year-$month-01 +1 month"));

[$prevYear, $prevMonth] = explode("-", $prev);
[$nextYear, $nextMonth] = explode("-", $next);

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Booking Returns</title>

<link rel="stylesheet" href="../style.css">



</head>

<body class="schedule-booking-page">

<header>
    <ul class="navbar-menu">
        <li><a href="cashier_index.php">Dashboard</a></li>
        <li><a href="create_order.php">Create Orders</a></li>
        <li><a href="view_orders.php">Orders</a></li>
        <li><a href="view_bookings.php">Bookings</a></li>
        <li><a href="scheduled_booking.php">Schedule Booking</a></li>
        <li><a href="cashier_orderHistory.php">History</a></li>
        <li><a href="cashier_logout.php">Logout</a></li>
    </ul>
</header>

<div class="container mt-4">

<h3>Booking Returns</h3>

<!-- ================= MONTH NAV ================= -->

<div class="nav-month">

    <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>&date=<?= $selectedDate ?>">⬅ Prev</a>

    <h4><?= date("F Y", strtotime($firstDay)) ?></h4>

    <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>&date=<?= $selectedDate ?>">Next ➡</a>

</div>

<!-- ================= RETURN CALENDAR (MATCHED DESIGN) ================= -->

<div class="daily_report_card">

    <div class="daily_report_calendar_header">
        <h5><?= date('F Y', strtotime("$year-$month-01")) ?></h5>
    </div>

    <div class="daily_report_calendar">

        <!-- DAY NAMES -->
        <?php for($i=0;$i<7;$i++): ?>
            <div class="daily_report_calendar_dayname">
                <?= ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$i] ?>
            </div>
        <?php endfor; ?>

        <!-- EMPTY SPACES -->
        <?php for($i=0;$i<$startDay;$i++): ?>
            <div></div>
        <?php endfor; ?>

        <!-- DAYS -->
        <?php for($d=1;$d<=$daysInMonth;$d++):

            $full = date("Y-m-d", strtotime("$year-$month-$d"));

            $returned = $calendar[$full]['returned'] ?? 0;
            $pending  = $calendar[$full]['pending'] ?? 0;

            $class = "";

            if($returned > 0 && $pending > 0){
                $class = "daily_report_both";
            }
            elseif($returned > 0){
                $class = "daily_report_bookings_only"; // you can rename to returned class later
            }
            elseif($pending > 0){
                $class = "daily_report_bookings_only";
            }
        ?>

        <a href="?date=<?= $full ?>&month=<?= $month ?>&year=<?= $year ?>"
           class="daily_report_day_box <?= $class ?> <?= $full==$selectedDate?'daily_report_active_day':'' ?>">

            <div class="daily_report_day_number">
                <?= $d ?>
            </div>

            <div class="daily_report_muted">
                Returned: <?= $returned ?>
            </div>

            <div class="daily_report_muted">
                Pending: <?= $pending ?>
            </div>

        </a>

        <?php endfor; ?>

    </div>
</div>

     

<!-- ================= SELECTED DAY ================= -->

<h4>Selected Date: <?= $selectedDate ?></h4>

<div class="schedule-booking-grid">

<?php if(empty($returnBookings)): ?>

    <div class="alert alert-warning">
        No bookings for this date
    </div>

<?php endif; ?>

<?php foreach($returnBookings as $r): ?>

<div class="schedule-booking-card-box">

    <h5><?= htmlspecialchars($r['name']) ?></h5>

    <!-- ================= APPOINTMENT TIME ================= -->
    <p>
        <b>Appointment Time:</b><br>
        <?= !empty($r['appointment_time'])
            ? date("M d, Y h:i A", strtotime($r['appointment_time']))
            : 'N/A'
        ?>
    </p>

    <!-- ================= RETURN TIME ================= -->
    <p>
        <b>Return Time:</b><br>
        <?= !empty($r['return_time'])
            ? date("M d, Y h:i A", strtotime($r['return_time']))
            : 'N/A'
        ?>
    </p>

    <p>
        <b>Contact:</b>
        <?= htmlspecialchars($r['contact']) ?>
    </p>

    <p>
        <b>Address:</b>
        <?= htmlspecialchars($r['address']) ?>
    </p>

    <hr>

    <p>
        <b>Total:</b>
        ₱<?= number_format($r['total'],2) ?>
    </p>

    <!-- ================= NOTES ================= -->
    <p>
        <b>Notes:</b><br>
        <?= !empty($r['delivery_note'])
            ? htmlspecialchars($r['delivery_note'])
            : 'No notes'
        ?>
    </p>

    <hr>

    <!-- ================= STATUS ================= -->

    <?php if(($r['status'] ?? '') == 'returned'): ?>

        <div class="alert alert-success">
            ✔ RETURNED
            <br>

            <?= !empty($r['returned_at'])
                ? date("M d, Y h:i A", strtotime($r['returned_at']))
                : ''
            ?>
        </div>

    <?php else: ?>

        <div class="alert alert-warning">
            Pending Return
        </div>

    <?php endif; ?>

</div>

<?php endforeach; ?>

</div>

</body>
</html>