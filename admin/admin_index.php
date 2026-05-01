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

/* ================= SAFE DATA (NO ERRORS) ================= */
$kpis = $adminData['kpis'] ?? [];

$ordersStatus = $adminData['ordersStatus'] ?? [
    "pending" => 0,
    "accepted" => 0,
    "rejected" => 0,
    "done" => 0
];

$kitchenStatus = $adminData['kitchenStatus'] ?? [
    "preparing" => 0,
    "ready" => 0,
    "done" => 0
];

$bookingsStatus = $adminData['bookingsStatus'] ?? [
    "pending" => 0,
    "accepted" => 0,
    "rejected" => 0
];

$bookingsPerDay = $adminData['bookingsPerDay'] ?? [];
$bestSelling = $adminData['bestSelling'] ?? [];

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

<!-- ===== NAVBAR ===== -->
<nav class="navbar">

    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>

    <ul class="navbar-menu">
        <li class="navbar-dropdown">
            <a href="#">Products ▼</a>
            <div class="navbar-dropdown-content">
                <a href="add_product.php">Add Product</a>
                <a href="product_list.php">Product List</a>
            </div>
        </li>

        <li><a href="booking_add.php">Booking</a></li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>

</nav>

<!-- ===== MAIN ===== -->
<div class="admin-dashboard-container">

    <div class="admin-dashboard-header">
        <h1 class="admin-dashboard-title">
            Welcome, <?php echo htmlspecialchars($admin_name); ?>!
        </h1>
        <p class="admin-dashboard-subtitle">
            <?php echo htmlspecialchars($admin_email); ?>
        </p>
    </div>

    <!-- KPI -->
    <div class="admin-dashboard-kpi-grid">

        <!-- ================= KPI CARD ================= -->
        <div class="admin-dashboard-kpi-card">
            <div class="admin-dashboard-kpi-value">
                ₱<?php echo number_format($kpis['todayRevenue'] ?? 0, 2); ?>
            </div>
            <div class="admin-dashboard-kpi-label">Today's Revenue</div>

            <!-- ✅ ADDED: PoP Indicator -->
            <?php $val = $kpis['todayRevenueChange'] ?? 0; ?>
            <div class="admin-dashboard-kpi-pop <?php echo ($val >= 0) ? 'positive' : 'negative'; ?>">
                <?php echo ($val >= 0) ? '▲' : '▼'; ?>
                <?php echo abs($val); ?>%
                <span>vs Prev Day</span>
            </div>
        </div>

        <div class="admin-dashboard-kpi-card">
            <div class="admin-dashboard-kpi-value">
                ₱<?php echo number_format($kpis['totalRevenue'] ?? 0, 2); ?>
            </div>
            <div class="admin-dashboard-kpi-label">Total Revenue</div>

            <!-- ✅ ADDED -->
            <?php $val = $kpis['totalRevenueChange'] ?? 0; ?>
            <div class="admin-dashboard-kpi-pop <?php echo ($val >= 0) ? 'positive' : 'negative'; ?>">
                <?php echo ($val >= 0) ? '▲' : '▼'; ?>
                <?php echo abs($val); ?>%
                <span>vs Prev Month</span>
            </div>
        </div>

        <div class="admin-dashboard-kpi-card">
            <div class="admin-dashboard-kpi-value">
                <?php echo $kpis['totalSales'] ?? 0; ?>
            </div>
            <div class="admin-dashboard-kpi-label">Total Sales</div>

            <!-- ✅ ADDED -->
            <?php $val = $kpis['totalSalesChange'] ?? 0; ?>
            <div class="admin-dashboard-kpi-pop <?php echo ($val >= 0) ? 'positive' : 'negative'; ?>">
                <?php echo ($val >= 0) ? '▲' : '▼'; ?>
                <?php echo abs($val); ?>%
                <span>vs Prev Month</span>
            </div>
        </div>

        <div class="admin-dashboard-kpi-card">
            <div class="admin-dashboard-kpi-value">
                <?php echo $kpis['totalUsers'] ?? 0; ?>
            </div>
            <div class="admin-dashboard-kpi-label">Total Users</div>

            <!-- ✅ ADDED -->
            <?php $val = $kpis['totalUsersChange'] ?? 0; ?>
            <div class="admin-dashboard-kpi-pop <?php echo ($val >= 0) ? 'positive' : 'negative'; ?>">
                <?php echo ($val >= 0) ? '▲' : '▼'; ?>
                <?php echo abs($val); ?>%
                <span>vs Prev Month</span>
            </div>
        </div>

        <div class="admin-dashboard-kpi-card">
            <div class="admin-dashboard-kpi-value">
                <?php echo $kpis['totalBookings'] ?? 0; ?>
            </div>
            <div class="admin-dashboard-kpi-label">Total Bookings</div>

            <!-- ✅ ADDED -->
            <?php $val = $kpis['totalBookingsChange'] ?? 0; ?>
            <div class="admin-dashboard-kpi-pop <?php echo ($val >= 0) ? 'positive' : 'negative'; ?>">
                <?php echo ($val >= 0) ? '▲' : '▼'; ?>
                <?php echo abs($val); ?>%
                <span>vs Prev Week</span>
            </div>
        </div>

    </div>

    <!-- CHARTS -->
    <div class="admin-dashboard-chart-grid">

        <div class="admin-dashboard-chart-box">
            <h3>Orders Status</h3>
            <canvas id="ordersChart"></canvas>
        </div>

        <div class="admin-dashboard-chart-box">
            <h3>Kitchen Orders Status</h3>
            <canvas id="kitchenChart"></canvas>
        </div>

        <div class="admin-dashboard-chart-box">
            <h3>Bookings Status</h3>
            <canvas id="bookingsStatusChart"></canvas>
        </div>

        <div class="admin-dashboard-chart-box">
            <h3>Bookings Per Day</h3>
            <canvas id="bookingsChart"></canvas>
        </div>

        <div class="admin-dashboard-chart-box">
            <h3>Best Selling Products</h3>
            <canvas id="bestSellingChart"></canvas>
        </div>

    </div>

</div>

<script>

/* ================= ORDERS ================= */
new Chart(document.getElementById('ordersChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pending','Accepted','Rejected'],
        datasets:[{
            data:[
                <?php echo $ordersStatus['pending'] ?? 0; ?>,
                <?php echo $ordersStatus['accepted'] ?? 0; ?>,
                <?php echo $ordersStatus['rejected'] ?? 0; ?>
            ],
            backgroundColor:['#d69016','#36980c','#e2120b']
        }]
    },
    options:{responsive:true, plugins:{legend:{position:'bottom'}}}
});

/* ================= KITCHEN ================= */
new Chart(document.getElementById('kitchenChart'),{
    type:'doughnut',
    data:{
        labels:['Preparing','Ready','Done'],
        datasets:[{
            data:[
                <?php echo $kitchenStatus['preparing'] ?? 0; ?>,
                <?php echo $kitchenStatus['ready'] ?? 0; ?>,
                <?php echo $kitchenStatus['done'] ?? 0; ?>
            ],
            backgroundColor:['#d69016','#1d83c7','#36980c']
        }]
    },
    options:{responsive:true, plugins:{legend:{position:'bottom'}}}
});

/* ================= BOOKINGS ================= */
new Chart(document.getElementById('bookingsStatusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pending','Accepted','Rejected'],
        datasets: [{
            data: [
                <?php echo $bookingsStatus['pending'] ?? 0; ?>,
                <?php echo $bookingsStatus['accepted'] ?? 0; ?>,
                <?php echo $bookingsStatus['rejected'] ?? 0; ?>
            ],
            backgroundColor: ['#d69016','#36980c','#e2120b']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

/* ================= BOOKINGS PER DAY ================= */

// ✅ FIX: always return array
let bookingsData = <?php echo !empty($bookingsPerDay) ? json_encode(array_values($bookingsPerDay)) : '[]'; ?>;

// ✅ FIX: prevent empty crash
if (bookingsData.length === 0) {
    bookingsData = [0];
}

// ✅ FIX: safe max/min
let maxBooking = Math.max(...bookingsData);
let minBooking = Math.min(...bookingsData);

// ✅ FIX: handle equal values (no infinite highlight issue)
let bookingColors = bookingsData.map(val => {
    if (maxBooking === minBooking) return '#1d83c7'; // all same
    if (val === maxBooking) return '#36980c'; // green
    if (val === minBooking) return '#e2120b'; // red
    return '#FFB74D'; // middle
});

new Chart(document.getElementById('bookingsChart'), {
    type: 'bar',
    data: {
        labels: <?php echo !empty($bookingsPerDay) ? json_encode(array_keys($bookingsPerDay)) : '["No Data"]'; ?>,
        datasets: [{
            label: 'Bookings',
            data: bookingsData,
            backgroundColor: bookingColors
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
/* ================= BEST SELLING ================= */

// ✅ FIX: always array
let bestSellingData = <?php echo !empty($bestSelling) ? json_encode(array_values($bestSelling)) : '[]'; ?>;

// ✅ FIX: prevent empty
if (bestSellingData.length === 0) {
    bestSellingData = [0];
}

// ✅ FIX: safe max/min
let maxProduct = Math.max(...bestSellingData);
let minProduct = Math.min(...bestSellingData);

// ✅ FIX: equal values protection
let productColors = bestSellingData.map(val => {
    if (maxProduct === minProduct) return '#098adf';
    if (val === maxProduct) return '#36980c';
    if (val === minProduct) return '#e2120b';
    return '#90CAF9';
});

new Chart(document.getElementById('bestSellingChart'), {
    type: 'bar',
    data: {
        labels: <?php echo !empty($bestSelling) ? json_encode(array_keys($bestSelling)) : '["No Data"]'; ?>,
        datasets: [{
            label: 'Items Sold',
            data: bestSellingData,
            backgroundColor: productColors
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }
    }
});

</script>

</body>
</html>