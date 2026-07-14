<?php
/**
 * seed.php — Populates Firebase with demo data to preview the full app.
 * Run: php seed.php
 * Idempotent: deletes existing data under each path before re-seeding.
 *
 * SAFETY: only runs when DEV_MODE is enabled.
 */

require_once __DIR__ . '/init.php';

if (!defined('DEV_MODE') || !DEV_MODE) {
    fwrite(STDERR, "Refusing to seed — DEV_MODE is not enabled.\n");
    exit(1);
}

echo "Seeding CRATES N' PLATES …\n";

$db = getDB();

/* ---------- helpers ---------- */
function delete_all(string $path): void {
    global $db;
    try {
        $existing = $db->retrieve($path);
        if (is_array($existing)) {
            foreach (array_keys($existing) as $k) {
                $db->delete($path, $k);
            }
        }
    } catch (Throwable) { /* empty */ }
}

function cnow(string $modify = ''): string {
    $t = $modify ? strtotime($modify) : time();
    return date('Y-m-d H:i:s', $t);
}

/* ================================================================
   1. SETTINGS — business info
   ================================================================ */
echo "  settings …\n";
$settings = [
    'business_name'   => "CRATES N' PLATES",
    'tagline'         => 'Diner',
    'address'         => 'Mabolo, Iloilo City Proper, Iloilo City, Philippines',
    'phone'           => '+63 (033) 320-0000',
    'hours'           => 'Tue–Sun · 11:00 AM – 10:00 PM (Closed Mondays)',
    'hero_title'      => 'Your table is waiting.',
    'hero_subtitle'   => 'Order ahead for pickup, reserve a table, or book equipment for your next celebration — all from one account.',
    'about_headline'  => 'Your trusted partner for events and celebrations.',
    'about_body'      => "From everyday meals to special gatherings, we bring quality food and reliable rental equipment to every table we serve in Iloilo City. Established 2016.",
    'about_stat1_num' => '10+', 'about_stat1_lbl' => 'Years Experience',
    'about_stat2_num' => '500+', 'about_stat2_lbl' => 'Events Served',
    'about_stat3_num' => '100%', 'about_stat3_lbl' => 'Satisfaction',
    'facebook_url'    => 'https://facebook.com/cratesnplates',
    'instagram_url'   => 'https://instagram.com/cratesnplates',
    'support_email'   => 'support@cratesnplates.ph',
];
try { $db->updateNode('/settings', $settings); } catch (Throwable $e) { echo "    FAIL: {$e->getMessage()}\n"; }

/* ================================================================
   2. PRODUCTS — menu items
   ================================================================ */
echo "  products …\n";
delete_all('/products');
$products = [
    // --- Mains ---
    ['name' => 'Chicken Inasal Quarter', 'category' => 'Mains', 'price' => 149, 'stock' => 25, 'description' => 'Grilled chicken thigh marinated in lemongrass & annatto, served with garlic rice & dipping sauce.', 'image' => ''],
    ['name' => 'Pork BBQ Skewers (3 pcs)', 'category' => 'Mains', 'price' => 119, 'stock' => 30, 'description' => 'Tender pork skewers basted in sweet-sticky BBQ glaze — a crowd favourite.', 'image' => ''],
    ['name' => 'Beef Tapa Silog', 'category' => 'Mains', 'price' => 179, 'stock' => 20, 'description' => 'Cured beef tapa, garlic fried rice, and a sunny-side egg. The breakfast staple served all day.', 'image' => ''],
    ['name' => 'Laing Pork Roll', 'category' => 'Mains', 'price' => 139, 'stock' => 15, 'description' => 'Taro leaves cooked in coconut milk with chili, wrapped around tender pork strips.', 'image' => ''],
    ['name' => 'Pancit Canton Combo', 'category' => 'Mains', 'price' => 159, 'stock' => 22, 'description' => 'Stir-fried egg noodles with shrimp, sausage, cabbage, and calamansi. Comes with a small spring roll.', 'image' => ''],
    ['name' => 'Crispy Pata Quarter', 'category' => 'Mains', 'price' => 299, 'stock' => 10, 'description' => 'Deep-fried pork hock, crackling skin, tender meat inside. Served with soy-vinegar dip.', 'image' => ''],

    // --- Rice Bowls ---
    ['name' => 'Garlic Rice Bowl', 'category' => 'Rice Bowls', 'price' => 35, 'stock' => 50, 'description' => 'Plain but perfect garlic fried rice.', 'image' => ''],
    ['name' => 'Java Rice Plate', 'category' => 'Rice Bowls', 'price' => 45, 'stock' => 40, 'description' => 'Yellow rice cooked in annatto with minced garlic.', 'image' => ''],

    // --- Sides ---
    ['name' => 'Atchara Papaya Salad', 'category' => 'Sides', 'price' => 35, 'stock' => 30, 'description' => 'Sweet-pickled green papaya shreds, a palate cleanser between bites.', 'image' => ''],
    ['name' => 'Lumpiang Shanghai (6 pcs)', 'category' => 'Sides', 'price' => 89, 'stock' => 35, 'description' => 'Crispy pork spring rolls with sweet-chili dip.', 'image' => ''],

    // --- Drinks ---
    ['name' => 'Buko Pandan Cooler', 'category' => 'Drinks', 'price' => 55, 'stock' => 40, 'description' => 'Young coconut strips in pandan jelly with crushed ice.', 'image' => ''],
    ['name' => 'Sago\'t Gulaman', 'category' => 'Drinks', 'price' => 45, 'stock' => 45, 'description' => 'Classic brown-sugar drink with sago pearls and gulaman cubes.', 'image' => ''],
    ['name' => 'Calamansi Juice (Iced)', 'category' => 'Drinks', 'price' => 40, 'stock' => 50, 'description' => 'Fresh-squeezed calamansi, sweetened, over ice.', 'image' => ''],
    ['name' => 'Bottled Water', 'category' => 'Drinks', 'price' => 20, 'stock' => 60, 'description' => '500ml mineral water.', 'image' => ''],

    // --- Desserts ---
    ['name' => 'Leche Flan Slice', 'category' => 'Desserts', 'price' => 65, 'stock' => 15, 'description' => 'Rich caramel custard slice — smooth, creamy, and golden on top.', 'image' => ''],
    ['name' => 'Halo-Halo Special', 'category' => 'Desserts', 'price' => 85, 'stock' => 20, 'description' => 'Shaved ice with ube, leche flan, macapuno, langka, pinipig, and a scoop of ice cream.', 'image' => ''],
];
foreach ($products as $p) {
    $p['created_at'] = cnow();
    try { $db->insert('/products', $p); } catch (Throwable $e) { echo "    FAIL: {$e->getMessage()}\n"; }
}

/* ================================================================
   3. RENT ITEMS — equipment for rent
   ================================================================ */
echo "  rent items …\n";
delete_all('/rent_items');
$rentItems = [
    ['name' => 'catering-tent',       'display_name' => 'Catering Tent (10×10 ft)',    'price' => 1500, 'quantity' => 8,  'image' => ''],
    ['name' => 'folding-table',       'display_name' => 'Folding Table (6 ft)',         'price' => 200,  'quantity' => 30, 'image' => ''],
    ['name' => 'folding-chair',       'display_name' => 'Folding Chair',               'price' => 50,   'quantity' => 80, 'image' => ''],
    ['name' => 'round-table-8',       'display_name' => 'Round Table (8-seater)',       'price' => 350,  'quantity' => 12, 'image' => ''],
    ['name' => 'chiavari-chair',       'display_name' => 'Chiavari Chair (Gold)',        'price' => 120,  'quantity' => 40, 'image' => ''],
    ['name' => 'dinner-plate-set',     'display_name' => 'Dinner Plate Set (6 pcs)',     'price' => 250,  'quantity' => 20, 'image' => ''],
    ['name' => 'glass-tumbler-set',   'display_name' => 'Glass Tumbler Set (6 pcs)',    'price' => 150,  'quantity' => 25, 'image' => ''],
    ['name' => 'buffet-warmer',       'display_name' => 'Buffet Warmer (Full-size)',    'price' => 800,  'quantity' => 4,  'image' => ''],
    ['name' => 'serving-platter-lrg', 'display_name' => 'Serving Platter (Large)',      'price' => 180,  'quantity' => 15, 'image' => ''],
];
foreach ($rentItems as $r) {
    $r['created_at'] = cnow();
    try { $db->insert('/rent_items', $r); } catch (Throwable $e) { echo "    FAIL: {$e->getMessage()}\n"; }
}

/* ================================================================
   4. USERS — sample customer accounts (email-verified, password "password123")
   ================================================================ */
echo "  users …\n";
delete_all('/user');
$users = [
    ['name' => 'Maria Santos',      'email' => 'maria@example.com',      'email_verified' => true, 'provider' => 'email'],
    ['name' => 'Juan Dela Cruz',    'email' => 'juan@example.com',       'email_verified' => true, 'provider' => 'email'],
    ['name' => 'Elena Rodriguez',   'email' => 'elena@example.com',      'email_verified' => true, 'provider' => 'email'],
    ['name' => 'Carlos Gomez',      'email' => 'carlos@example.com',     'email_verified' => false, 'provider' => 'email'],
];
$hash = password_hash('password123', PASSWORD_BCRYPT);
foreach ($users as $u) {
    $u['password_hash']  = $hash;
    $u['otp']            = null;
    $u['otp_expires']    = null;
    $u['profile_image']  = '';
    $u['created_at']     = cnow('-' . random_int(1, 14) . ' days');
    try { $db->insert('/user', $u); } catch (Throwable $e) { echo "    FAIL: {$e->getMessage()}\n"; }
}

/* ================================================================
   5. ADMIN + STAFF accounts (password: "admin1234" / "cashier123" / "kitchen123")
   ================================================================ */
echo "  admin accounts …\n";
delete_all('/admins');
$adminHash = password_hash('admin1234', PASSWORD_BCRYPT);
try {
    $db->insert('/admins', ['name' => 'Owner Admin', 'email' => 'admin@cratesnplates.ph', 'password_hash' => $adminHash, 'created_at' => cnow('-30 days')]);
} catch (Throwable $e) { echo "    FAIL: {$e->getMessage()}\n"; }

echo "  cashiers …\n";
delete_all('/cashiers');
foreach ([
    ['name' => 'Ana Reyes',     'email' => 'ana@cratesnplates.ph'],
    ['name' => 'Ben Torres',    'email' => 'ben@cratesnplates.ph'],
] as $c) {
    $c['password_hash'] = password_hash('cashier123', PASSWORD_BCRYPT);
    $c['created_at']    = cnow('-14 days');
    try { $db->insert('/cashiers', $c); } catch (Throwable $e) { echo "    FAIL: {$e->getMessage()}\n"; }
}

echo "  kitchen staff …\n";
delete_all('/kitchen');
foreach ([
    ['name' => 'Chef Dante',  'email' => 'dante@cratesnplates.ph'],
] as $k) {
    $k['password_hash'] = password_hash('kitchen123', PASSWORD_BCRYPT);
    $k['created_at']    = cnow('-7 days');
    try { $db->insert('/kitchen', $k); } catch (Throwable $e) { echo "    FAIL: {$e->getMessage()}\n"; }
}

/* ================================================================
   6. ORDERS — spread across the past 7 days
   ================================================================ */
echo "  orders …\n";
delete_all('/orders');

// Fetch product keys for realistic item references
$productRows = rows($db->retrieve('/products'));
$productKeys = array_keys($productRows);
$productData = [];
foreach ($productRows as $k => $p) {
    if (is_array($p)) $productData[$k] = $p;
}

if (!$productData) {
    echo "    WARN: no products found, orders will use inline item stubs\n";
}

$customerEmails = ['maria@example.com','juan@example.com','elena@example.com'];
$customerNames  = ['Maria Santos','Juan Dela Cruz','Elena Rodriguez'];

$statuses = ['pending','accepted','preparing','ready','done','cancelled'];
$paymentMethods = ['gcash','counter'];
$paymentStatuses = [
    'pending' => ['no_payment_required','pending_verification','paid'],
    'accepted' => ['pending_verification','paid'],
    'preparing' => ['paid'],
    'ready' => ['paid'],
    'done' => ['paid'],
    'cancelled' => ['no_payment_required','pending_verification'],
];

for ($i = 0; $i < 28; $i++) {
    $daysAgo = random_int(0, 6);
    $timeOffset = random_int(0, 600); // minutes within the day
    $created = cnow("-{$daysAgo} days +{$timeOffset} minutes");

    $status = $statuses[array_rand($statuses)];
    $method = $paymentMethods[array_rand($paymentMethods)];
    $payStatuses = $paymentStatuses[$status];
    $pay = $payStatuses[array_rand($payStatuses)];
    $ci = array_rand($customerEmails);

    // Build 1–5 random items from existing products, or fallback stubs
    $items = [];
    $total = 0;
    $numItems = random_int(1, 4);
    for ($j = 0; $j < $numItems; $j++) {
        if ($productData && random_int(0, 2) > 0) {
            $pk = array_rand($productData);
            $p = $productData[$pk];
            $qty = random_int(1, 3);
            $price = (float) ($p['price'] ?? 50);
            $subtotal = round($price * $qty, 2);
            $items[] = [
                'id'       => $pk,
                'name'     => $p['name'] ?? 'Item',
                'qty'      => $qty,
                'price'    => $price,
                'subtotal' => $subtotal,
            ];
            $total += $subtotal;
        } else {
            $names = ['Extra Rice','Side of Atchara','Calamansi Juice','Bottled Water'];
            $price = [15, 20, 30, 40][$j % 4];
            $qty = 1;
            $items[] = ['name' => $names[$j % 4], 'qty' => $qty, 'price' => $price, 'subtotal' => $price];
            $total += $price;
        }
    }

    $order = [
        'user_id'          => '',
        'user_email'       => $customerEmails[$ci],
        'user_name'        => $customerNames[$ci],
        'items'            => $items,
        'total'            => round($total, 2),
        'full_name'        => $customerNames[$ci],
        'contact'          => '+639' . random_int(100000000, 999999999),
        'address'          => ['Mabolo','Lapuz','Jaro','Mandurriao','Molo'][array_rand(['Mabolo','Lapuz','Jaro','Mandurriao','Molo'])],
        'pickup_time'      => sprintf('%02d:%02d', random_int(11, 20), random_int(0, 1) * 30),
        'payment_method'   => $method,
        'payment_status'   => $pay,
        'payment_verified' => in_array($pay, ['paid','pending_verification'], true),
        'receipt'          => $method === 'gcash' && random_int(0, 1) ? 'receipt-' . $i . '.jpg' : null,
        'status'           => $status,
        'created_at'       => $created,
    ];
    try { $db->insert('/orders', $order); } catch (Throwable $e) { echo "    FAIL: {$e->getMessage()}\n"; }
}

/* ================================================================
   7. BOOKINGS — rent reservations
   ================================================================ */
echo "  bookings …\n";
delete_all('/bookings');

// Fetch rent item keys
$rentRows = rows($db->retrieve('/rent_items'));
$rentKeys = array_keys($rentRows);

for ($i = 0; $i < 12; $i++) {
    $daysAgo = random_int(-2, 5); // negative = future
    $created = cnow();
    $appt = cnow("+{$daysAgo} days +" . random_int(480, 1020) . " minutes");

    $status = ['pending','confirmed','completed','cancelled'][array_rand(['pending','confirmed','completed','cancelled'])];
    $method = ['gcash','counter'][array_rand(['gcash','counter'])];
    $ci = array_rand($customerEmails);

    // Pick 1–3 rent items
    $items = [];
    $total = 0;
    $numRent = random_int(1, 3);
    $selected = (array) array_rand(array_flip($rentKeys), min($numRent, count($rentKeys)));
    foreach ($selected as $rk) {
        if (!isset($rentRows[$rk])) continue;
        $r = $rentRows[$rk];
        $qty = random_int(1, 5);
        $subtotal = round((float) ($r['price'] ?? 100) * $qty, 2);
        $items[] = ['id' => $rk, 'name' => $r['display_name'] ?? $r['name'] ?? 'Item', 'qty' => $qty, 'price' => (float) ($r['price'] ?? 100), 'subtotal' => $subtotal];
        $total += $subtotal;
    }

    if (!$items) continue;

    $booking = [
        'user_id'          => '',
        'user_email'       => $customerEmails[$ci],
        'user_name'        => $customerNames[$ci],
        'items'            => $items,
        'total'            => round($total, 2),
        'full_name'        => $customerNames[$ci],
        'contact'          => '+639' . random_int(100000000, 999999999),
        'address'          => 'Mabolo, Iloilo City',
        'appointment_time' => $appt,
        'return_time'      => $daysAgo >= 0 ? cnow("+{$daysAgo} days +" . random_int(1020, 1320) . " minutes") : null,
        'payment_method'   => $method,
        'payment_status'   => $status === 'cancelled' ? 'no_payment_required' : ($method === 'gcash' ? 'paid' : 'no_payment_required'),
        'status'           => $status,
        'created_by'       => random_int(0, 1) ? 'admin' : 'customer',
        'created_at'       => $created,
    ];
    try { $db->insert('/bookings', $booking); } catch (Throwable $e) { echo "    FAIL: {$e->getMessage()}\n"; }
}

echo "Done.\n";
echo "\n";
echo "Test accounts:\n";
echo "  Admin:   admin@cratesnplates.ph / admin1234\n";
echo "  Cashier: ana@cratesnplates.ph / cashier123\n";
echo "  Kitchen: dante@cratesnplates.ph / kitchen123\n";
echo "  User:    maria@example.com / password123\n";
echo "  User:    juan@example.com / password123\n";
