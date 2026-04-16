<?php
include(__DIR__ . "/../config.php");

if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'];
$admin_email = $_SESSION['admin_email'];

$adminData = include(__DIR__ . "/admin_process.php");
$orders = $adminData['orders'];
$bookings = $adminData['bookings'];
$kpis = $adminData['kpis'];
$ordersStatus = $adminData['ordersStatus'];
$kitchenStatus = $adminData['kitchenStatus'];
$bookingsPerDay = $adminData['bookingsPerDay'];
$bestSelling = $adminData['bestSelling'] ?? [];

/* ✅ ADD THIS */
$bookingsStatus = $adminData['bookingsStatus'] ?? [
    "pending" => 0,
    "accepted" => 0,
    "rejected" => 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">Admin Dashboard</div>
    <ul class="navbar-menu">
        <li class="navbar-dropdown">
            <a href="#">Products ▼</a>
            <div class="navbar-dropdown-content">
                <a href="add_product.php">Add Product</a>
                <a href="product_list.php">Product List</a>
            </div>
        </li>
        <li><a href="admin_log.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</h1>
        <p class="page-subtitle"><?php echo htmlspecialchars($admin_email); ?></p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-value">₱<?php echo number_format($kpis['todayRevenue'], 2); ?></div>
            <div class="kpi-label">Today's Revenue</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value">₱<?php echo number_format($kpis['totalRevenue'], 2); ?></div>
            <div class="kpi-label">Total Revenue</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?php echo $kpis['totalSales']; ?></div>
            <div class="kpi-label">Total Sales</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?php echo $kpis['totalUsers']; ?></div>
            <div class="kpi-label">Total Users</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?php echo $kpis['totalBookings']; ?></div>
            <div class="kpi-label">Total Bookings</div>
        </div>
    </div>


    
    <div class="chart-grid">
        <div class="chart-box">
            <h3>Orders Status</h3>
            <canvas id="ordersChart"></canvas>
        </div>

        <div class="chart-box">
            <h3>Kitchen Orders Status</h3>
            <canvas id="kitchenChart"></canvas>
        </div>

        <!-- ✅ NEW BOOKINGS STATUS CHART -->
        <div class="chart-box">
            <h3>Bookings Status</h3>
            <canvas id="bookingsStatusChart"></canvas>
        </div>

        <div class="chart-box">
            <h3>Bookings Per Day</h3>
            <canvas id="bookingsChart"></canvas>
        </div>

        <div class="chart-box">
            <h3>Best Selling Products</h3>
            <canvas id="bestSellingChart"></canvas>
        </div>
    </div>
</div>

<script>
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
            backgroundColor:['#E0A030','#5D8A4A','#B85450']
        }]
    },
    options:{responsive:true, plugins:{legend:{position:'bottom'}}}
});

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
            backgroundColor:['#E0A030','#3498DB','#5D8A4A']
        }]
    },
    options:{responsive:true, plugins:{legend:{position:'bottom'}}}
});

/* ✅ BOOKINGS STATUS CHART */
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
            backgroundColor: ['#E0A030','#5D8A4A','#B85450']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

new Chart(document.getElementById('bookingsChart'), {
    type: 'bar',
    data: {
        labels: [<?php echo !empty($bookingsPerDay) ? '"' . implode('","', array_keys($bookingsPerDay)) . '"' : ''; ?>],
        datasets: [{
            label: 'Bookings',
            data: [<?php echo !empty($bookingsPerDay) ? implode(',', array_values($bookingsPerDay)) : 0; ?>],
            backgroundColor: '#6B4423'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

new Chart(document.getElementById('bestSellingChart'), {
    type: 'bar',
    data: {
        labels: [<?php echo !empty($bestSelling) ? '"' . implode('","', array_keys($bestSelling)) . '"' : ''; ?>],
        datasets: [{
            label: 'Items Sold',
            data: [<?php echo !empty($bestSelling) ? implode(',', array_values($bestSelling)) : 0; ?>],
            backgroundColor: '#5D8A4A'
        }]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true } }
    }
});
</script>

</body>
</html>