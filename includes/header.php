<?php
/** header.php — premium layout shell. Expects $pageTitle, optional $activeNav, $layout. */
security_headers();
$pageTitle  = $pageTitle  ?? BRAND_NAME;
$activeNav  = $activeNav  ?? '';
$layout     = $layout     ?? '';
$role       = active_role();
$mainClass  = 'app-main' . ($layout === 'narrow' ? ' app-main--narrow' : ($layout === 'wide' ? ' app-main--wide' : ''));

// Build nav per role
$navItems = [];
switch ($role) {
    case 'customer':
        $navItems = [
            '/user/about.php'      => ['About', 'about'],
            '/user/products.php'   => ['Shop', 'shop'],
            '/user/booking.php'    => ['Rent', 'rent'],
            '/user/your_orders.php' => ['My Orders', 'orders'],
        ];
        break;
    case 'cashier':
        $navItems = [
            '/cashier/'              => ['Orders', 'orders'],
            '/cashier/order_now.php' => ['Order Now', 'ordernow'],
            '/cashier/bookings.php'  => ['Bookings', 'bookings'],
            '/cashier/history.php'   => ['History', 'history'],
        ];
        break;
    case 'kitchen':
        $navItems = [
            '/kitchen/'        => ['Orders', 'orders'],
            '/kitchen/history.php' => ['History', 'history'],
        ];
        break;
    case 'admin':
        $navItems = [
            '/admin/'                => ['Dashboard', 'dash'],
            '/admin/products.php'    => ['Products', 'products'],
            '/admin/rent_items.php'  => ['Rent Items', 'rent'],
            '/admin/bookings.php'    => ['Bookings', 'bookings'],
            '/admin/staff.php'       => ['Staff', 'staff'],
            '/admin/reports.php'     => ['Reports', 'reports'],
            '/admin/settings.php'    => ['Settings', 'settings'],
        ];
        break;
}
$logoutUrl = [
    'customer' => '/user/logout.php',
    'cashier'  => '/cashier/logout.php',
    'kitchen'  => '/kitchen/logout.php',
    'admin'    => '/admin/logout.php',
][$role] ?? '/user/login.php';

$userLabel = match ($role) {
    'customer' => ($_SESSION['user_name']  ?? 'Account'),
    'cashier'  => ($_SESSION['cashier_name'] ?? 'Cashier'),
    'kitchen'  => ($_SESSION['kitchen_name'] ?? 'Kitchen'),
    'admin'    => 'Administrator',
    default    => 'Sign in',
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> &middot; <?= e(BRAND_NAME) ?></title>
  <meta name="description" content="<?= e(BRAND_NAME) ?> — <?= e(BRAND_TAGLINE) ?>">
  <script>(function(){try{var t=localStorage.getItem('ss-theme');var m=window.matchMedia('(prefers-color-scheme: dark)').matches;if(t==='dark'||(!t&&m)){document.documentElement.setAttribute('data-theme','dark')}}catch(e){}})();</script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="icon" href="/assets/img/logo.png">
</head>
<body>
<div class="app-shell">
  <header class="topbar">
    <div class="topbar__inner">
      <a class="brand" href="<?= $role === 'guest' ? '/user/login.php' : (
          $role === 'customer' ? '/user/products.php' :
          ($role === 'admin' ? '/admin/' : ('/'.$role.'/'))
      ) ?>">
        <span class="brand__mark"><img src="/assets/img/logo.png" alt="CRATES N' PLATES" class="brand__logo"></span>
        <span>
          <span class="brand__name"><?= e(BRAND_NAME) ?></span><br>
          <span class="brand__tag"><?= e(BRAND_TAGLINE) ?></span>
        </span>
      </a>

      <?php if ($navItems): ?>
      <nav class="topbar__nav" id="topnav">
        <?php foreach ($navItems as $href => [$label, $key]): ?>
          <a class="nav-link <?= $activeNav === $key ? 'is-active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($role === 'customer'): ?>
          <a class="nav-link cart-pill<?= $activeNav === 'cart' ? ' is-active' : '' ?>" href="/user/cart.php">Cart
            <?php if (cart_count() > 0): ?><span class="count"><?= cart_count() ?></span><?php endif; ?>
          </a>
          <a class="nav-link <?= $activeNav === 'profile' ? 'is-active' : '' ?>" href="/user/your_profile.php"><?= e($userLabel) ?></a>
        <?php else: ?>
          <span class="nav-link" style="cursor:default;color:var(--muted)"><?= e($userLabel) ?></span>
        <?php endif; ?>
        <a class="nav-link nav-cta btn btn--outline btn--sm" href="<?= e($logoutUrl) ?>">Sign out</a>
      </nav>
      <?php endif; ?>
      <span class="topbar__spacer"></span>
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode" aria-pressed="false" data-theme-toggle title="Toggle theme">
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <?php if ($navItems): ?>
      <button class="topbar__burger" type="button" aria-label="Toggle menu" aria-expanded="false" data-nav-toggle>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
      <?php endif; ?>
    </div>
  </header>

  <main class="<?= $mainClass ?>">
    <?php foreach (get_flashes() as $f): ?>
      <div class="alert alert--<?= e($f['type']) ?>" role="status">
        <span><?= e($f['message']) ?></span>
      </div>
    <?php endforeach; ?>
