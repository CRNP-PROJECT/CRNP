<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

date_default_timezone_set("Asia/Manila");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$data_raw = $rdb->retrieve("/cashier_bookinghistory");
$data = json_decode($data_raw, true) ?? [];

// 🔥 FILTER (GET CATEGORY)
$filter = $_GET['filter'] ?? 'all';
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>Booking History</title>

<style>
.tab-bar{
    margin: 15px 0;
}

.tab-bar a{
    padding: 8px 15px;
    margin-right: 5px;
    text-decoration: none;
    border-radius: 5px;
    background: #eee;
    color: #333;
    font-weight: bold;
}

.tab-bar a.active{
    background: #333;
    color: #fff;
}
</style>

</head>
<body>

<nav class="navbar">
    <a href="cashier_index.php" class="navbar-brand">Cashier Dashboard</a>
    <ul class="navbar-menu">
        
        <li><a href="view_bookings.php">View Bookings</a></li>
        
    </ul>
</nav>

<div class="container">

    <div class="page-header flex-between">
        <h1 class="page-title">📋 Booking History</h1>
    </div>

    <!-- 🔥 FILTER TABS -->
    <div class="tab-bar">
        <a href="?filter=all" class="<?= $filter=='all'?'active':'' ?>">All</a>
        <a href="?filter=accepted" class="<?= $filter=='accepted'?'active':'' ?>">Accepted</a>
        <a href="?filter=rejected" class="<?= $filter=='rejected'?'active':'' ?>">Rejected</a>
    </div>

    <?php if(empty($data)): ?>
        <div class="card">
            <p class="text-center text-muted">No booking history yet.</p>
        </div>
    <?php else: ?>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Processed By</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach($data as $id => $b): 
                        $status = strtolower($b['final_status'] ?? '');
                        $displayStatus = strtoupper($status);

                        // 🔥 FILTER LOGIC
                        if($filter != 'all' && $status != $filter){
                            continue;
                        }
                    ?>
                    <tr>

                        <td><?= htmlspecialchars($id); ?></td>

                        <td><?= htmlspecialchars($b['full_name'] ?? 'N/A'); ?></td>

                        <td><?= htmlspecialchars($b['user_email'] ?? 'None'); ?></td>

                        <td>
                            <?php
                            $time = $b['cashier_action_time'] ?? '';
                            echo !empty($time) ? date("M d, Y h:i A", strtotime($time)) : "N/A";
                            ?>
                        </td>

                        <td>
                            <?php if($status === 'accepted'): ?>
                                <span style="color:green;font-weight:bold;">ACCEPTED</span>
                            <?php elseif($status === 'rejected'): ?>
                                <span style="color:red;font-weight:bold;">REJECTED</span>
                            <?php else: ?>
                                <span><?= $displayStatus ?></span>
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($b['processed_by'] ?? ''); ?></td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    <?php endif; ?>

</div>

</body>
</html>