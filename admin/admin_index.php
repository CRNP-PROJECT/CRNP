<?php
session_start();

include(__DIR__ . "/../config.php");

if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'];
$admin_email = $_SESSION['admin_email'];

$adminData = include(__DIR__ . "/admin_process.php");
$chartData = include(__DIR__ . "/admin_charts.php");

/* ================= SAFE DATA ================= */
$kpis = $chartData['kpis'] ?? [];

$ordersStatus = $chartData['ordersStatus'] ?? [
    "pending" => 0,
    "accepted" => 0,
    "rejected" => 0,
    "done" => 0
];

$kitchenStatus = $chartData['kitchenStatus'] ?? [
    "preparing" => 0,
    "ready" => 0,
    "done" => 0
];

$bookingsStatus = $chartData['bookingsStatus'] ?? [
    "pending" => 0,
    "accepted" => 0,
    "rejected" => 0
];

$bookingsPerDay = $chartData['bookingsPerDay'] ?? [];
$bestSelling = $chartData['bestSelling'] ?? [];

/* NEW ANALYTICS */
$revenuePerDay = $chartData['revenuePerDay'] ?? [];
$ordersByHour = $chartData['ordersByHour'] ?? [];
$bookingItems = $chartData['bookingItems'] ?? [];

/* ================= NEW FEATURES ================= */
$ordersByDayOfWeek = $chartData['ordersByDayOfWeek'] ?? [];
$categorySales = $chartData['categorySales'] ?? [];

/* ================= PER DAY KPI ================= */
$todayRevenue = $kpis['todayRevenue'] ?? 0;
$todaySales   = $kpis['todaySales'] ?? 0;

/* AOV PER DAY */
$aov = ($todaySales > 0) ? ($todayRevenue / $todaySales) : 0;

/* ================= BOOKING KPIs (NEW) ================= */
$bookingTotalSales = $chartData['bookingTotalSales'] ?? 0;
$bookingTotalOrders = $chartData['bookingTotalOrders'] ?? 0;

$chartData['latestBookings'] = $latestBookings;

$bookingAOV = ($bookingTotalOrders > 0)
    ? ($bookingTotalSales / $bookingTotalOrders)
    : 0;

/* ================= BOOKING PER DAY AOV (NEW) ================= */
$bookingRevenuePerDay = $chartData['bookingRevenuePerDay'] ?? [];
$bookingOrdersPerDay  = $chartData['bookingOrdersPerDay'] ?? [];

$bookingAovPerDay = [];

foreach($bookingRevenuePerDay as $day => $rev){
    $orders = $bookingOrdersPerDay[$day] ?? 0;
    $bookingAovPerDay[$day] = ($orders > 0) ? ($rev / $orders) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body class="admin-dashboard-body">

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
        <li><a href="admin_index.php" class="active">Admin Dashboard</a></li>
        <li class="navbar-dropdown">
            <a href="#">Products ▼</a>
            <div class="navbar-dropdown-content">
                <a href="add_product.php">Add Product</a>
                <a href="product_list.php">Product List</a>
            </div>
        </li>

        <li><a href="booking_add.php">Booking Items</a></li>
        <li><a href="booking_reserve.php">Booking List</a></li>
        <li><a href="booking_add.php">Booking</a></li>
        <li><a href="daily_report.php">Daily report</a></li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>
</nav>

<div class="admin-dashboard-container">

<div class="admin-dashboard-header">
    <h1 class="admin-dashboard-title">
        Welcome, <?= htmlspecialchars($admin_name) ?>!
    </h1>
    <p class="admin-dashboard-subtitle">
        <?= htmlspecialchars($admin_email) ?>
    </p>
</div>

<!-- KPI -->
<div class="admin-dashboard-kpi-grid">

    <div class="admin-dashboard-kpi-card">
        <div class="admin-dashboard-kpi-value">₱<?= number_format($todayRevenue,2) ?></div>
        <div class="admin-dashboard-kpi-label">Today's Revenue</div>
    </div>

    <div class="admin-dashboard-kpi-card">
        <div class="admin-dashboard-kpi-value"><?= $todaySales ?></div>
        <div class="admin-dashboard-kpi-label">Today's Orders</div>
    </div>

    <div class="admin-dashboard-kpi-card">
        <div class="admin-dashboard-kpi-value">₱<?= number_format($aov,2) ?></div>
        <div class="admin-dashboard-kpi-label">Today's AOV</div>
    </div>

    <!-- TODAY BOOKING KPI -->
    <div class="admin-dashboard-kpi-card">
        <div class="admin-dashboard-kpi-value">₱<?= number_format($todayBookingSales,2) ?></div>
        <div class="admin-dashboard-kpi-label">Today's Booking Sales</div>
    </div>

    <div class="admin-dashboard-kpi-card">
        <div class="admin-dashboard-kpi-value"><?= $todayBookingOrders ?></div>
        <div class="admin-dashboard-kpi-label">Today's Booking Orders</div>
    </div>

    <div class="admin-dashboard-kpi-card">
        <div class="admin-dashboard-kpi-value">₱<?= number_format($todayBookingAOV,2) ?></div>
        <div class="admin-dashboard-kpi-label">Today's Booking AOV</div>
    </div>

    <div class="admin-dashboard-kpi-card">
        <div class="admin-dashboard-kpi-value"><?= $kpis['totalUsers'] ?? 0 ?></div>
        <div class="admin-dashboard-kpi-label">Total Users</div>
    </div>

    

</div>

<!-- STATUS -->
<div class="section-title">STATUS OVERVIEW</div>

<div class="admin-dashboard-chart-grid">

    <div class="admin-dashboard-chart-box">
        <h3>Orders Status</h3>
        <canvas id="ordersChart"></canvas>
    </div>

    <div class="admin-dashboard-chart-box">
        <h3>Kitchen Status</h3>
        <canvas id="kitchenChart"></canvas>
    </div>

    <div class="admin-dashboard-chart-box">
        <h3>Booking Status</h3>
        <canvas id="bookingsStatusChart"></canvas>
    </div>

</div>

<!-- BOOKINGS -->
<div class="section-title">BOOKINGS ANALYTICS</div>

<div class="admin-dashboard-chart-grid">

    
    <div class="admin-dashboard-chart-box">
    <h3>Latest Day Bookings</h3>
    <canvas id="latestBookingsChart"></canvas>

</div>

<div class="admin-dashboard-chart-box">
    <h3>Today's Most Booked Items</h3>
    <canvas id="todayMostBookedChart"></canvas>
</div>

    <div class="admin-dashboard-chart-box">
    <h3>Latest Booking Revenue</h3>
    <canvas id="bookingLatestRevenueChart"></canvas>

    
</div>
</div>


</div>

<!-- ORDERS -->
<div class="section-title">ORDERS ANALYTICS</div>

<div class="admin-dashboard-chart-grid">
    <div class="admin-dashboard-chart-box">
    <h3>Category Sales (Latest Day Orders)</h3>
    <canvas id="categoryLatestChart"></canvas>
</div>

    <div class="admin-dashboard-chart-box">
        <h3>Revenue Today</h3>
        <canvas id="revenueChart"></canvas>
    </div>

    <div class="admin-dashboard-chart-grid">

    <div class="admin-dashboard-chart-box">
    <h3>Best Selling Per Day</h3>
    <canvas id="bestSellingDayChart"></canvas>
</div>

</div>

</div>

<!-- ADVANCED -->
<div class="section-title">PERFORMANCE ANALYTICS</div>

<div class="admin-dashboard-chart-grid">

   
    <div class="admin-dashboard-chart-box">
        <h3>Orders by Hour</h3>
        <canvas id="hourChart"></canvas>
    </div>
    <div class="admin-dashboard-chart-box">
        <h3>Category Sales</h3>
        <canvas id="categoryChart"></canvas>
    </div>

    <div class="admin-dashboard-chart-box">
        <h3>Orders by Day of Week</h3>
        <canvas id="weekChart"></canvas>
    </div>
    <div class="admin-dashboard-chart-box">
        <h3>Best Selling Products</h3>
        <canvas id="bestSellingChart"></canvas>
    </div>
    <div class="admin-dashboard-chart-box">
        <h3>Bookings Per Day</h3>
        <canvas id="bookingsChart"></canvas>
    </div>
    
    <div class="admin-dashboard-chart-box">
        <h3>Most Booked Items</h3>
        <canvas id="bookingItemsChart"></canvas>
    </div>

</div>

</div>

<script>

/* ORDERS */
new Chart(document.getElementById('ordersChart'), {
type:'doughnut',
data:{
labels:['Pending','Accepted','Rejected'],
datasets:[{
data:[
<?= $ordersStatus['pending'] ?>,
<?= $ordersStatus['accepted'] ?>,
<?= $ordersStatus['rejected'] ?>
],
backgroundColor:['#d69016','#36980c','#e2120b']
}]
}
});

new Chart(document.getElementById('kitchenChart'), {
type: 'bar',
data: {
labels: ['Preparing','Ready','Done'],
datasets: [{
    label: 'Kitchen Status',
    data: [
        <?= $kitchenStatus['preparing'] ?>,
        <?= $kitchenStatus['ready'] ?>,
        <?= $kitchenStatus['done'] ?>
    ],
    backgroundColor: ['#d69016','#1d83c7','#36980c']
}]
},
options: {
    responsive: true,
    scales: {
        x: { stacked: true },
        y: { stacked: true, beginAtZero: true }
    }
}
});

/* BOOKINGS STATUS */
new Chart(document.getElementById('bookingsStatusChart'), {
type:'doughnut',
data:{
labels:['Pending','Accepted','Rejected'],
datasets:[{
data:[
<?= $bookingsStatus['pending'] ?>,
<?= $bookingsStatus['accepted'] ?>,
<?= $bookingsStatus['rejected'] ?>
],
backgroundColor:['#d69016','#36980c','#e2120b']
}]
}
});

/* BOOKINGS PER DAY */
new Chart(document.getElementById('bookingsChart'),{
type:'bar',
data:{
labels:<?= json_encode(array_keys($bookingsPerDay)) ?>,
datasets:[{
label:'Bookings',
data:<?= json_encode(array_values($bookingsPerDay)) ?>,
backgroundColor:'#FFB74D'
}]
}
});

/* BEST SELLING */
new Chart(document.getElementById('bestSellingChart'),{
type:'bar',
data:{
labels:<?= json_encode(array_keys($bestSelling)) ?>,
datasets:[{
label:'Items Sold',
data:<?= json_encode(array_values($bestSelling)) ?>,
backgroundColor:'#90CAF9'
}]
}
});

new Chart(document.getElementById('categoryChart'),{
type:'bar',
data:{
labels:["Foods","Drinks","Beverages"],
datasets:[{
data:[
<?= $categorySales['Foods'] ?? 0 ?>,
<?= $categorySales['Drinks'] ?? 0 ?>,
<?= $categorySales['Beverages'] ?? 0 ?>
],
backgroundColor:['#4CAF50','#2196F3','#FF9800']
}]
},
options:{
plugins:{legend:{display:false}},
scales:{y:{beginAtZero:true}}
}
});

new Chart(document.getElementById('revenueChart'),{
type:'bar',
data:{
labels:["Today"],
datasets:[{
label:'Revenue',
data:[<?= $todayRevenue ?>],

backgroundColor:'#36980c',
borderRadius:8,
borderSkipped:false
}]
},
options:{
responsive:true,
scales:{
y:{
beginAtZero:true
}
}
}
});

/* HOUR */
new Chart(document.getElementById('hourChart'),{
type:'bar',
data:{
labels:<?= json_encode(array_keys($ordersByHour)) ?>,
datasets:[{
label:'Orders',
data:<?= json_encode(array_values($ordersByHour)) ?>,
backgroundColor:'#1d83c7'
}]
}
});

/* WEEK */
new Chart(document.getElementById('weekChart'),{
type:'bar',
data:{
labels:<?= json_encode(array_keys($ordersByDayOfWeek)) ?>,
datasets:[{
label:'Orders',
data:<?= json_encode(array_values($ordersByDayOfWeek)) ?>,
backgroundColor:'#9C27B0'
}]
}
});

/*booking item chart in perf */
new Chart(document.getElementById('bookingItemsChart'),{
type:'bar',
data:{
labels:<?= json_encode(array_keys($bookingItems)) ?>,
datasets:[{
label:'Booked Items',
data:<?= json_encode(array_values($bookingItems)) ?>,
backgroundColor:'#FF9800'
}]
},
options:{
indexAxis:'y',   
scales:{
x:{beginAtZero:true}
}
}
});

/* BEST SELLING PER DAY */
new Chart(document.getElementById('bestSellingDayChart'), {
type: 'bar',
data: {
labels: <?= json_encode($bestSellingPerDayLabels ?? []) ?>,
datasets: [{
label: 'Best Selling (Latest Day)',
data: <?= json_encode($bestSellingPerDayData ?? []) ?>,
backgroundColor: '#FF7043'
}]
}
});

/*todays category latest chart  */
new Chart(document.getElementById('categoryLatestChart'), {
type: 'doughnut',
data: {
labels: ["Foods","Drinks","Beverages"],
datasets: [{
data: [
<?= $chartData['categorySalesLatestDay']['Foods'] ?? 0 ?>,
<?= $chartData['categorySalesLatestDay']['Drinks'] ?? 0 ?>,
<?= $chartData['categorySalesLatestDay']['Beverages'] ?? 0 ?>
],
backgroundColor: ['#4CAF50','#2196F3','#FF9800']
}]
}
});
new Chart(document.getElementById('bookingLatestRevenueChart'), {
type: 'bar',
data: {
labels: ["<?= $chartData['latestBookingDate'] ?? 'Latest' ?>"],
datasets: [{
label: 'Revenue',
data: [<?= $chartData['bookingRevenueLatestDay'] ?? 0 ?>],
backgroundColor: '#36980c'
}]
}
});

/*latest booking chart */
const latestBookings = <?= json_encode($chartData['latestBookings'] ?? []) ?>;

const ctx = document.getElementById("latestBookingsChart").getContext("2d");

new Chart(ctx, {
    type: "bar",
    data: {
        labels: latestBookings.labels,
        datasets: [{
            label: "Bookings Today",
            data: latestBookings.data,

            // 🔥 different colors per bar
            backgroundColor: [
                '#1d83c7',
                '#36980c',
                '#d69016',
                '#e2120b',
                '#9C27B0',
                '#FF9800',
                '#4CAF50',
                '#03A9F4',
                '#795548',
                '#607D8B'
            ],

            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});

/*todays most booked in booking chart */
new Chart(document.getElementById('todayMostBookedChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($todayMostBooked)) ?>,
        datasets: [{
            label: 'Bookings Today',
            data: <?= json_encode(array_values($todayMostBooked)) ?>,

            
            backgroundColor: [
                '#1d83c7',
                '#36980c',
                '#d69016',
                '#e2120b',
                '#9C27B0',
                '#FF9800',
                '#4CAF50',
                '#03A9F4'
            ]
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        scales: {
            x: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});

<!-- your existing charts scripts above -->


// 🔥 MIDNIGHT AUTO REFRESH
function checkMidnightReset() {
    const now = new Date();

    const hours = now.getHours();
    const minutes = now.getMinutes();
    const seconds = now.getSeconds();

    if (hours === 0 && minutes === 0 && seconds === 0) {
        location.reload();
    }
}

setInterval(checkMidnightReset, 1000);



</script>

</body>
</html>