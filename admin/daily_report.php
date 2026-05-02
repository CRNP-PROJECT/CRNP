<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

$rdb = new firebaseRDB($databaseURL);

/* ================= FETCH ================= */

$orders   = json_decode($rdb->retrieve("/orders"), true) ?? [];
$bookings = json_decode($rdb->retrieve("/bookings"), true) ?? [];

/* ================= FILTER ================= */

$view = $_GET['view'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');

/* ================= MONTH NAVIGATION (ADDED) ================= */

$prevMonth = date('Y-m-d', strtotime($date . ' -1 month'));
$nextMonth = date('Y-m-d', strtotime($date . ' +1 month'));

/* ================= WEEK RANGE ================= */

$timestamp = strtotime($date);
$dayOfWeek = date('N', $timestamp);

$weekStart = date('Y-m-d', strtotime($date . ' -' . ($dayOfWeek - 1) . ' days'));
$weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));

/* ================= HELPERS ================= */

function parseDateOnly($str){
    if(!$str) return '';
    $t = strtotime($str);
    return $t ? date('Y-m-d', $t) : substr($str,0,10);
}

function peso($num){
    return "₱" . number_format($num,2);
}

/* ================= DATA ================= */

$calendar = [];

$selectedOrders = [];
$selectedBookings = [];

$dailyOrders = 0;
$dailyBookings = 0;
$dailyRevenue = 0;
$dailyBookingRevenue = 0;

/* ================= CHART DATA ================= */

$labels = [];
$revenueData = [];
$orderCountData = [];
$bookingCountData = [];

/* ================= ORDERS ================= */

foreach($orders as $o){

    if(!is_array($o)) continue;

    $d = parseDateOnly($o['created_at'] ?? '');
    if(!$d) continue;

    if(!isset($calendar[$d])){
        $calendar[$d] = [
            'orders'=>0,
            'bookings'=>0,
            'orderRevenue'=>0,
            'bookingRevenue'=>0
        ];
    }

    $calendar[$d]['orders']++;
    $calendar[$d]['orderRevenue'] += floatval($o['total'] ?? 0);

    if($d == $date){
        $dailyOrders++;
        $dailyRevenue += floatval($o['total'] ?? 0);
        $selectedOrders[] = $o;
    }
}

/* ================= BOOKINGS (ONLY ACCEPTED) ================= */

foreach($bookings as $b){

    if(!is_array($b)) continue;

    $status = strtolower($b['status'] ?? '');

    if($status !== 'accepted') continue;

    $d = parseDateOnly($b['created_at'] ?? '');
    if(!$d) continue;

    if(!isset($calendar[$d])){
        $calendar[$d] = [
            'orders'=>0,
            'bookings'=>0,
            'orderRevenue'=>0,
            'bookingRevenue'=>0
        ];
    }

    $calendar[$d]['bookings']++;

    $amount = floatval($b['booking_total'] ?? 0);
    $calendar[$d]['bookingRevenue'] += $amount;

    if($d == $date){
        $dailyBookings++;
        $dailyBookingRevenue += $amount;
        $selectedBookings[] = $b;
    }
}


/* ================= BUILD CHART ================= */

ksort($calendar);

foreach($calendar as $d => $v){
    $labels[] = $d;
    $revenueData[] = $v['orderRevenue'] + $v['bookingRevenue'];
    $orderCountData[] = $v['orders'];
    $bookingCountData[] = $v['bookings'];
}

/* ================= CALENDAR ================= */

$year  = date('Y', strtotime($date));
$month = date('m', strtotime($date));

$firstDay = date('Y-m-01', strtotime($date));
$daysInMonth = date('t', strtotime($firstDay));
$startDay = date('w', strtotime($firstDay));
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>POS Analytics Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{background:#f4f6f9;font-family:Arial;}
.card-box{border:none;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,.08);background:#fff;}
.calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;}
.day-box{background:#fff;border-radius:12px;padding:10px;text-decoration:none;color:#000;min-height:95px;box-shadow:0 2px 6px rgba(0,0,0,.05);}
.day-box:hover{transform:scale(1.02);}
.day-number{font-weight:bold;}
.orders-only{background:#cfe2ff;}
.bookings-only{background:#fff3cd;}
.both{background:#d1e7dd;}
.active-day{border:2px solid #0d6efd;}
.muted{font-size:12px;color:#666;}
.kpi{text-align:center;padding:15px;}
</style>
</head>

<body>

<div class="container py-4">

<h3 class="mb-3">📊 POS Analytics Dashboard</h3>

<!-- VIEW BUTTONS -->
<div class="mb-2 text-center">

<a href="?view=daily&date=<?= $date ?>" class="btn btn-sm <?= $view=='daily'?'btn-primary':'btn-outline-primary' ?>">Daily</a>

<a href="?view=weekly&date=<?= $date ?>" class="btn btn-sm <?= $view=='weekly'?'btn-primary':'btn-outline-primary' ?>">Weekly</a>

<a href="?view=monthly&date=<?= $date ?>" class="btn btn-sm <?= $view=='monthly'?'btn-primary':'btn-outline-primary' ?>">Monthly</a>

</div>

<!-- MONTH NAVIGATION (ADDED) -->
<div class="text-center mb-3">

<a class="btn btn-sm btn-outline-dark" href="?view=monthly&date=<?= $prevMonth ?>">⬅ Prev Month</a>

<input type="month"
       value="<?= date('Y-m', strtotime($date)) ?>"
       onchange="location='?view=monthly&date='+this.value+'-01'">

<a class="btn btn-sm btn-outline-dark" href="?view=monthly&date=<?= $nextMonth ?>">Next Month ➡</a>

</div>

<div class="text-center mb-3">
<small class="text-muted">
📅 Daily • 📆 Weekly • 📊 Monthly
</small>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">

<div class="col-md-3 card card-box kpi">
<h6>Orders</h6>
<h4><?= $dailyOrders ?></h4>
</div>

<div class="col-md-3 card card-box kpi">
<h6>Revenue</h6>
<h4><?= peso($dailyRevenue) ?></h4>
</div>

<div class="col-md-3 card card-box kpi">
<h6>Bookings</h6>
<h4><?= $dailyBookings ?></h4>
</div>

<div class="col-md-3 card card-box kpi">
<h6>Booking Revenue</h6>
<h4><?= peso($dailyBookingRevenue) ?></h4>
</div>

</div>

<!-- CALENDAR -->
<div class="card card-box p-3 mb-4">

<h5><?= date('F Y', strtotime($date)) ?></h5>

<div class="calendar">

<?php for($i=0;$i<7;$i++): ?>
<div class="text-center fw-bold"><?= ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$i] ?></div>
<?php endfor; ?>

<?php for($i=0;$i<$startDay;$i++) echo "<div></div>"; ?>

<?php for($d=1;$d<=$daysInMonth;$d++):
$full = date('Y-m-d', strtotime("$year-$month-$d"));

$o = $calendar[$full]['orders'] ?? 0;
$b = $calendar[$full]['bookings'] ?? 0;
$or = $calendar[$full]['orderRevenue'] ?? 0;
$br = $calendar[$full]['bookingRevenue'] ?? 0;

$class = "";
if($o>0 && $b>0) $class="both";
elseif($o>0) $class="orders-only";
elseif($b>0) $class="bookings-only";
?>

<a href="?date=<?= $full ?>" class="day-box <?= $class ?> <?= $full==$date?'active-day':'' ?>">
<div class="day-number"><?= $d ?></div>
<div class="muted">Orders: <?= $o ?></div>
<div class="muted">Bookings: <?= $b ?></div>
<div class="muted"><?= peso($or + $br) ?></div>
</a>


<?php endfor; ?>

</div>
</div>

<!-- CHARTS -->
<div class="row g-3 mb-4">

<div class="col-md-6">
<div class="card card-box p-3">
<h5>📈 Revenue Trend</h5>
<canvas id="revenueChart"></canvas>
</div>
</div>

<div class="col-md-6">
<div class="card card-box p-3">
<h5>📊 Orders vs Bookings</h5>
<canvas id="barChart"></canvas>
</div>
</div>

</div>

</div>

<script>
const labels = <?= json_encode($labels) ?>;
const revenue = <?= json_encode($revenueData) ?>;
const orders = <?= json_encode($orderCountData) ?>;
const bookings = <?= json_encode($bookingCountData) ?>;

new Chart(document.getElementById("revenueChart"), {
    type: 'line',
    data: { labels, datasets: [{ label:'Revenue', data: revenue, borderWidth:2 }] }
});

new Chart(document.getElementById("barChart"), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            { label:'Orders', data: orders },
            { label:'Bookings', data: bookings }
        ]
    }
});
</script>
<!-- DETAILS -->
<div class="row">

<div class="col-md-6">
<div class="card card-box p-3">
<h5>Orders</h5>
<?php foreach($selectedOrders as $o): ?>
<div class="border-bottom py-2">
<b><?= peso($o['total'] ?? 0) ?></b><br>
<?php
$products = $o['products'] ?? [];

if(is_string($products)){
    $products = json_decode($products, true);
}

if(is_array($products) && !empty($products)){
    echo "<small><b>Items Ordered:</b><br>";

    foreach($products as $p){
        $name = $p['name'] ?? $p['product_name'] ?? 'Item';
        $qty  = $p['qty'] ?? $p['quantity'] ?? 1;

        echo "• $name x $qty<br>";
    }

    echo "</small><br>";
}

/* ================= PAYMENT METHOD ================= */
$payment = $o['payment_method'] ?? 'N/A';
echo "<small><b>Payment Method:</b> $payment</small><br>";

?>
<small><?= $o['created_at'] ?? '' ?></small>
</div>
<?php endforeach; ?>
</div>
</div>

<div class="col-md-6">
<div class="card card-box p-3">
<h5>Bookings (Accepted Only)</h5>
<?php foreach($selectedBookings as $b): ?>
<div class="border-bottom py-2">
<b><?= peso($b['booking_total'] ?? 0) ?></b><br>
<?php
$item = $b['items'] ?? 'No Item';
$payment = $b['payment_method'] ?? 'N/A';

echo "<small><b>Booking Details:</b><br>";

// ITEMS
echo "• Items: ";

if (is_array($item)) {
    foreach ($item as $i) {
        // if item is string
        if (is_string($i)) {
            echo $i . " ";
        } else {
            $name = $i['name'] ?? 'Item';
            $qty  = $i['qty'] ?? 1;
            echo "$name x $qty<br>";
        }
    }
} else {
    echo $item;
}

echo "<br>";

// PAYMENT
echo "• Payment: $payment";

echo "</small><br>";
?>
<small><?= $b['created_at'] ?? '' ?></small>
</div>
<?php endforeach; ?>

</body>
</html>

