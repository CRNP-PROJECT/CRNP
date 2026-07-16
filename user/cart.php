<?php
session_start();
include(__DIR__ . "/../config.php");
include(__DIR__ . "/../firebaseRDB.php");

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? "User";
$rdb = new firebaseRDB($databaseURL);
$cart = $_SESSION['cart'] ?? [];
$total = 0;
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <title>Your Cart</title>
</head>

<body class="cart-page-body">


<header class="navbar">
    <div class="navbar-brand-container">
        <img src="../img/logo.png" class="logo">
    </div>
    <div class="navbar-right">
        <ul class="navbar-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="cart.php" class="active">Cart</a></li>
            <li><a href="aboutus.php">About</a></li>
        </ul>
        <div class="navbar-dropdown">
            <span class="navbar-user-btn">
                <i class="fa-regular fa-user"></i> <?php echo htmlspecialchars($username); ?> ▼
            </span>
            <div class="navbar-dropdown-content">
                <a href="your_profile.php">My Profile</a>
                <a href="your_orders.php">Your Orders</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>

<div class="cart-wrapper">

    <h1 class="cart-title">
        YOUR CART (<?php echo array_sum($cart); ?> ITEMS)
    </h1>

    <?php if(empty($cart)): ?>

        <p class="cart-empty">Your cart is empty.</p>

    <?php else: ?>

        <!-- TABLE HEADER -->
        <div class="cart-table-header">
            <span>Product</span>
            <span>Price</span>
            <span>Quantity</span>
            <span>Action</span>
            <span>Subtotal</span>
        </div>

        <?php foreach($cart as $id => $qty):

            $res = $rdb->retrieve("/products/$id");
            $product = json_decode($res, true);

            if(!$product) continue;

            $price = floatval($product['price']);
            $subtotal = $price * $qty;
            $total += $subtotal;
        ?>

        <!-- CART ROW -->
        <div class="cart-row">

            <!-- PRODUCT -->
            <div class="cart-product">

                <img
                    src="<?php echo htmlspecialchars($product['image']); ?>"
                    class="cart-product-img"
                    alt="Product Image"
                >

                <span>
                    <?php echo htmlspecialchars($product['name']); ?>
                </span>

            </div>

            <!-- PRICE -->
            <div
                class="cart-price"
                data-price="<?php echo $price; ?>"
            >
                ₱<?php echo number_format($price,2); ?>
            </div>

            <!-- QUANTITY -->
            <div class="cart-qty">

                <form
                    method="POST"
                    action="process.php"
                    class="cart-qty-form"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="update_cart"
                    >

                    <input
                        type="hidden"
                        name="product_id"
                        value="<?php echo $id; ?>"
                    >

                    <button
                        type="button"
                        class="cart-qty-btn minus-btn"
                    >
                        -
                    </button>

                    <input
                        type="number"
                        name="quantity"
                        value="<?php echo $qty; ?>"
                        min="1"
                        class="cart-qty-input"
                    >

                    <button
                        type="button"
                        class="cart-qty-btn plus-btn"
                    >
                        +
                    </button>

                </form>

            </div>

            <!-- DELETE -->
            <div class="cart-remove">

                <a
                href="process.php?action=remove_cart&id=<?php echo $id; ?>"
                onclick="return removeCartItem(this)">Delete</a>

            </div>

            <!-- SUBTOTAL -->
            <div class="cart-subtotal">
                ₱<?php echo number_format($subtotal,2); ?>
            </div>

        </div>

        <?php endforeach; ?>

        <!-- SUMMARY -->
        <div class="cart-summary">

    <div class="cart-total">

        <h3 class="cart-total-title">
            TOTAL
        </h3>

        <h3 id="cartTotal" class="cart-total-price">
            ₱<?php echo number_format($total,2); ?>
        </h3>

    </div>

    <div class="cart-summary-actions">

        <a href="checkout.php" class="cart-checkout-btn">
            Proceed to Checkout
        </a>

        <a href="products.php" class="cart-continue-link">
            Continue Shopping
        </a>

    </div>

</div>

    <?php endif; ?>

</div>

<script>

function updateTotals() {

    let grandTotal = 0;

    document.querySelectorAll('.cart-row').forEach(row => {

        const price = parseFloat(
            row.querySelector('.cart-price').dataset.price
        );

        const qty = parseInt(
            row.querySelector('.cart-qty-input').value
        ) || 1;

        const subtotal = price * qty;

        row.querySelector('.cart-subtotal').innerText =
            '₱' + subtotal.toFixed(2);

        grandTotal += subtotal;
    });

    const totalElement = document.getElementById('cartTotal');

    if(totalElement){
        totalElement.innerText =
            '₱' + grandTotal.toFixed(2);
    }
}

/* PLUS BUTTON */
document.querySelectorAll('.plus-btn').forEach(btn => {

    btn.addEventListener('click', function(){

        const input = this.parentElement.querySelector('.cart-qty-input');

        input.stepUp();

        updateTotals();
    });

});

/* MINUS BUTTON */
document.querySelectorAll('.minus-btn').forEach(btn => {

    btn.addEventListener('click', function(){

        const input = this.parentElement.querySelector('.cart-qty-input');

        if(parseInt(input.value) > 1){
            input.stepDown();
        }

        updateTotals();
    });

});

/* MANUAL INPUT */
document.querySelectorAll('.cart-qty-input').forEach(input => {

    input.addEventListener('input', function(){

        if(this.value < 1){
            this.value = 1;
        }

        updateTotals();
    });

});

</script>

<div id="cartToast" class="cart-toast">
    Item removed from cart
</div>

<script>
function removeCartItem(link){

    const row = link.closest('.cart-row');
    const toast = document.getElementById('cartToast');

    if(row){
        row.style.opacity = "0";

        setTimeout(() => {
            row.remove();
        }, 250);
    }

    toast.innerText = "Item removed from cart";
    toast.classList.add('show');

    setTimeout(() => {
        window.location.href = link.href;
    }, 800);

    return false;
}
</script>

</body>
</html>