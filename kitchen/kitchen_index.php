<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['kitchen_email'])){
    header("Location: kitchen_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

$orders_raw = $rdb->retrieve("/orders");
$orders = json_decode($orders_raw, true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../styles.css">

<title>Kitchen Dashboard</title>
</head>

<body class="kitchen-dashboard">

<nav class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" alt="Logo" class="logo">
    </div>
    <a href="kitchen_index.php" class="navbar-brand">
    </a>
    <ul class="navbar-menu">
        <li>
            <a href="kitchen_logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </li>
    </ul>
</nav>

<div class="container">

    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-fire-burner"></i> Kitchen Orders
        </h1>

        <p class="page-subtitle">
            <?php echo htmlspecialchars($_SESSION['kitchen_email']); ?>
        </p>
    </div>

    <div class="orders-grid">

    <?php if(empty($orders)): ?>
        <div class="card full">
            <p class="text-center text-muted">No orders found.</p>
        </div>
    <?php else: ?>

        <?php foreach($orders as $id => $order): 
            if(($order['status'] ?? '') === 'accepted'):
                $kitchen_status = $order['kitchen_status'] ?? 'pending';
        ?>

        <div class="card">

            <div class="card-header">
                <strong>
                    <i class="fa-solid fa-user"></i>
                    <?php echo htmlspecialchars($order['full_name']); ?>
                </strong>

                <span class="badge badge-<?php echo $kitchen_status; ?>">
                    <?php echo strtoupper($kitchen_status); ?>
                </span>
            </div>

            <div class="products">
                <?php foreach($order['products'] as $p): ?>
                    <div class="product-item">
                        <i class="fa-solid fa-bowl-food"></i>
                        <?php echo htmlspecialchars($p['name']); ?>
                        <span class="text-muted"> × <?php echo intval($p['qty']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ✅ SHOW BUTTONS ONLY IF NOT DONE -->
            <?php if($kitchen_status !== 'done'): ?>
            <form method="POST" action="kitchen_process.php" class="card-actions">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo $id; ?>">

                <button type="submit" name="status" value="preparing" class="btn btn-secondary">
                    Preparing
                </button>

                <button type="submit" name="status" value="ready" class="btn btn-primary">
                    Ready
                </button>

                <button type="submit" name="status" value="done" class="btn btn-success">
                    Done
                </button>
            </form>

            <?php else: ?>
            <!-- ✅ COMPLETED STATE -->
            <div class="card-complete">
                <i class="fa-solid fa-circle-check"></i> Completed
            </div>
            <?php endif; ?>

        </div>

        <?php endif; endforeach; ?>

    <?php endif; ?>

    </div>
</div>

</body>
</html>