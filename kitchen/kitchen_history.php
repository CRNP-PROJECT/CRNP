<?php
session_start();

include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if(!isset($_SESSION['kitchen_email'])){
    header("Location: kitchen_login.php");
    exit;
}

$rdb = new firebaseRDB($databaseURL);

// ================= GET HISTORY =================
$rawHistory = json_decode($rdb->retrieve("/kitchen_history"), true) ?? [];

$history = [];

// ================= FILTER =================
foreach($rawHistory as $id => $order){

    $status = strtolower($order['kitchen_status'] ?? '');

    // show only valid kitchen tracked orders
    if(!in_array($status, ['accepted','preparing','ready','done'])){
        continue;
    }

    $order['_id'] = $id;
    $history[$id] = $order;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">

<title>Kitchen History</title>
</head>

<body class="kitchen-history-page">
<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>
    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="kitchen_index.php">Dashboard</a></li>
            <li><a href="kitchen_history.php" class="active">History</a></li>
            <li><a href="kitchen_logout.php">Logout</a></li>
        </ul>
    </div>
</header>

<div class="kitchen-history-container">

    <div class="kitchen-history-header-wrapper">
        <h1 class="kitchen-history-title">Kitchen</h1>
        <h1 class="kitchen-history-title">History</h1>
    </div>
    
    <p class="kitchen-history-subtitle">Completed kitchen orders</p>

    <div id="history-container" class="kitchen-history-grid">

<?php if(empty($history)): ?>
    <div class="kitchen-history-empty">
        No completed orders yet.
    </div>
<?php else: ?>

<?php foreach($history as $order): ?>

<div class="kitchen-history-card">

    <div class="kitchen-history-card-header">
        <div>
            <div class="kitchen-history-name"><?= htmlspecialchars($order['full_name'] ?? 'N/A') ?></div>
            <div class="kitchen-history-type"><?= strtoupper($order['order_type'] ?? 'ORDER') ?></div>
        </div>
        <span class="kitchen-history-badge <?= strtolower($order['kitchen_status'] ?? '') ?>">
            <?= strtoupper($order['kitchen_status'] ?? 'UNKNOWN') ?>
        </span>
    </div>

    <div class="kitchen-history-info">
        <p><span>Order ID:</span> <?= $order['_id'] ?></p>
        <p><span>Total:</span> ₱<?= number_format($order['total'] ?? 0, 2) ?></p>
        <p><span>Completed:</span> <?= $order['kitchen_action_time'] ?? 'N/A' ?></p>
    </div>

    <div class="kitchen-history-items">
        <?php foreach(($order['products'] ?? []) as $item): ?>
            <div class="kitchen-history-item">
                <?= htmlspecialchars($item['name']) ?>
                <span>x<?= intval($item['qty']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php endforeach; ?>
<?php endif; ?>

</div>
</div>

<script>
/* PURPOSE: Async Live Update Engine
   This script runs in the background to automatically update the completed orders grid 
   without requiring the user to manually hit refresh in their web browser.
*/
let lastHTML = document.getElementById("history-container").innerHTML;

async function loadHistory() {
    try {
        const res = await fetch("fetch_kitchen_history.php");

        if(!res.ok) return;

        const data = await res.json();

        if(data && data.html){
            document.getElementById("history-container").innerHTML = data.html;
            lastHTML = data.html;
        }

    } catch (err) {
        document.getElementById("history-container").innerHTML = lastHTML;
    }
}

/* Continuously checks for new changes every 5 seconds */
setInterval(loadHistory, 5000);


/* PURPOSE: Scroll-Activated Header Fading Animation
   This listener watches window scrolling and dynamically drops the opacity of the 
   titles to 0 once the user scrolls past 140px down, keeping the screen clutter-free.
*/
document.addEventListener("DOMContentLoaded", function () {
    const historyTitles = document.querySelectorAll('.kitchen-history-title');
    
    window.addEventListener('scroll', function () {
        let scrollTop = window.scrollY;
        
        /* Calculates opacity reduction based on scroll pixel distance */
        let newOpacity = 1 - (scrollTop / 140);
        
        /* Clamp values to stay safely within standard 0.0 to 1.0 rendering parameters */
        if (newOpacity < 0) newOpacity = 0;
        if (newOpacity > 1) newOpacity = 1;
        
        /* Directly updates CSS opacity rule layers across each title row line */
        historyTitles.forEach(title => {
            title.style.opacity = newOpacity;
        });
    });
});
</script>

</body>
</html>