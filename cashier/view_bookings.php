<?php

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['cashier_email'])){
    header("Location: cashier_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$bookings_raw = $rdb->retrieve("/bookings");
$bookings = json_decode($bookings_raw, true);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../styles.css">
<title>View Bookings</title>
</head>
<body>

<nav class="navbar">
    <a href="cashier_index.php" class="navbar-brand">Cashier Dashboard</a>
    <ul class="navbar-menu">
        <li><a href="view_orders.php">View Orders</a></li>
        <li><a href="view_bookings.php">View Bookings</a></li>
        <li><a href="cashier_logout.php">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="page-header flex-between">
        <div>
            <h1 class="page-title">Booking Reservations</h1>
        </div>
        <a href="cashier_index.php" class="btn btn-secondary btn-sm">← Back</a>
    </div>

    <?php if(empty($bookings)): ?>
        <div class="card">
            <p class="text-center text-muted">No bookings found.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>User Email</th>
                        <th>Full Name</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Date & Time</th>
                        <th>Tables</th>
                        <th>Chairs</th>
                        <th>Skirting</th>
                        <th>Status</th> <!-- ✅ NEW -->
                        <th>Action</th> <!-- ✅ NEW -->
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($bookings as $id => $b): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($b['user_email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($b['full_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($b['contact_number'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($b['address'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($b['appointment_time'] ?? ''); ?></td>
                        <td><?php echo intval($b['tables_qty'] ?? 0); ?></td>
                        <td><?php echo intval($b['chairs_qty'] ?? 0); ?></td>
                        <td>
                            <?php 
                            if(isset($b['skirting']) && is_array($b['skirting'])){
                                foreach($b['skirting'] as $s){
                                    echo htmlspecialchars($s['color']) . " (x" . intval($s['qty']) . ")<br>";
                                }
                            } else {
                                echo "-";
                            }
                            ?>
                        </td>

                        <!-- ✅ STATUS -->
                        <td>
                            <?php
                            $status = $b['status'] ?? 'pending';

                            if($status == "accepted"){
                                echo "<span style='color:green;'>Accepted</span>";
                            } elseif($status == "rejected"){
                                echo "<span style='color:red;'>Rejected</span>";
                            } else {
                                echo "<span style='color:orange;'>Pending</span>";
                            }
                            ?>
                            <td>
    <?php
    $status = $b['status'] ?? 'pending';

    if($status == 'pending'){
    ?>
        <form action="cashier_process.php" method="POST" style="display:inline;">
            <input type="hidden" name="action" value="update_booking_status">
            <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
            <input type="hidden" name="status" value="accepted">
            <button type="submit">Accept</button>
        </form>

        <form action="cashier_process.php" method="POST" style="display:inline;">
            <input type="hidden" name="action" value="update_booking_status">
            <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
            <input type="hidden" name="status" value="rejected">
            <button type="submit">Reject</button>
        </form>
    <?php
    } else {
        echo "<span style='color:gray;'>No actions available</span>";
    }
    ?>
</td>
                        <td><?php echo htmlspecialchars($b['created_at'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>