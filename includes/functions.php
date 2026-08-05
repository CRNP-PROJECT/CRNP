<?php
/**
 * functions.php — shared helpers used across every role.
 */

/* ---------- security headers (C2) ---------- */
/**
 * Send a baseline set of security headers. Call as early as possible
 * (before any HTML output) on every page, including standalone auth pages.
 */
function security_headers(): void {
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://accounts.google.com; frame-src 'self' https://accounts.google.com https://www.google.com https://maps.google.com; connect-src 'self'; object-src 'none'; base-uri 'self'");
}

/* ---------- CSRF protection (C3) ---------- */
/**
 * Get (or lazily create) the per-session CSRF token.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
/**
 * Render a hidden <input> containing the CSRF token. Drop inside every POST form.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}
/**
 * Verify the CSRF token submitted with a POST request. Bails on mismatch.
 * Call at the very top of every POST handler block.
 */
function csrf_verify(): void {
    $t = $_POST['csrf_token'] ?? '';
    if (empty($t) || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
        http_response_code(419);
        flash('Security token expired. Please try again.', 'danger');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}

/* ---------- rate limiting (C5) ----------
 * Simple file-backed sliding-window limiter. Returns true when the action
 * is allowed (and records the attempt), false when the limit is exceeded.
 * The bucket file lives in sys_get_temp_dir() and is keyed by an opaque
 * string (e.g. 'login_' . $email). */
function rate_limit(string $key, int $maxAttempts, int $windowSecs): bool {
    $file = sys_get_temp_dir() . '/rl_' . md5($key) . '.json';
    $now  = time();
    $data = [];
    if (is_file($file)) {
        $data = json_decode((string)file_get_contents($file), true) ?: [];
    }
    // purge attempts outside the window
    $data = array_values(array_filter($data, fn($t) => $t > $now - $windowSecs));
    if (count($data) >= $maxAttempts) {
        return false; // limit exceeded
    }
    $data[] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

/* ---------- output / flow ---------- */
function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}
function now(): string {
    return date('Y-m-d H:i:s');
}
function money($n): string {
    return "\u{20B1}" . number_format((float)$n, 2); // ₱
}
function gen_otp(): string {
    try {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        return str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
function rows($data): array {
    return is_array($data) ? $data : [];
}

/* ---------- flash messages ---------- */
function flash(string $message, string $type = 'info'): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}
function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Firebase filtering (PHP-side; avoids indexOn rules) ---------- */
function filter_by(array $rows, string $key, $val): array {
    $out = [];
    foreach ($rows as $id => $row) {
        if (is_array($row) && array_key_exists($key, $row) && strcasecmp((string)$row[$key], (string)$val) === 0) {
            $out[$id] = $row;
        }
    }
    return $out;
}
function filter_like(array $rows, string $key, $val): array {
    $out = [];
    $v = strtolower((string)$val);
    foreach ($rows as $id => $row) {
        if (is_array($row) && array_key_exists($key, $row) && strpos(strtolower((string)$row[$key]), $v) !== false) {
            $out[$id] = $row;
        }
    }
    return $out;
}

/* ---------- file uploads (C4 hardened) ---------- */
/**
 * Save an uploaded file. Returns the generated filename or null if no file.
 * Verifies the real MIME type via finfo AND proves it's a real image with
 * getimagesize() — never trusts the client-supplied extension.
 * @throws Exception on validation / IO failure.
 */
function save_upload(string $field, string $destDir, array $allowed = ['jpg', 'jpeg', 'png', 'webp'], int $maxMB = 5): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error (code ' . $_FILES[$field]['error'] . ').');
    }
    if ($_FILES[$field]['size'] > $maxMB * 1024 * 1024) {
        throw new Exception("File exceeds {$maxMB}MB limit.");
    }
    $tmp = $_FILES[$field]['tmp_name'];
    // verify real MIME via finfo (do NOT trust the client extension)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($mimeToExt[$mime])) {
        throw new Exception('Invalid file type. Only images are allowed.');
    }
    $ext = $mimeToExt[$mime];
    if (!in_array($ext, $allowed)) {
        throw new Exception('File type not permitted.');
    }
    // verify it's a real image (rejects polyglots / crafted headers)
    $imgInfo = @getimagesize($tmp);
    if ($imgInfo === false) {
        throw new Exception('File is not a valid image.');
    }
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0775, true);
    }
    $name   = bin2hex(random_bytes(16)) . '.' . $ext;
    $target = rtrim($destDir, '/') . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        throw new Exception('Failed to save uploaded file.');
    }
    return $name;
}

/** Web URL for an uploaded asset given a category subpath and filename. */
function upload_web(string $category, ?string $filename): string {
    if (!$filename) {
        return '/assets/img/placeholder.svg';
    }
    /* External URLs (e.g. Google profile pictures) — return as-is. */
    if (preg_match('#^https?://#i', $filename)) {
        return $filename;
    }
    /* base64 data URIs (Firebase dual-save) — return as-is. */
    if (str_starts_with($filename, 'b64:')) {
        return $filename;
    }
    if ($category === 'admin/item') {
        return '/assets/img/products/' . rawurlencode($filename);
    }
    return UPLOAD_WEB . '/' . $category . '/' . rawurlencode($filename);
}

/* ---------- base64 image storage (Firebase) + local save ---------- */
/**
 * Save uploaded image locally to UPLOAD_ROOT/admin/item/ AND return a
 * "b64:<base64data>" string for Firebase storage.
 * @throws Exception on validation / IO failure.
 */
function upload_to_base64(string $field, string $localDir = '', int $maxMB = 5): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error (code ' . $_FILES[$field]['error'] . ').');
    }
    if ($_FILES[$field]['size'] > $maxMB * 1024 * 1024) {
        throw new Exception("File exceeds {$maxMB}MB limit.");
    }
    $tmp = $_FILES[$field]['tmp_name'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($mimeToExt[$mime])) {
        throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
    }
    $imgInfo = @getimagesize($tmp);
    if ($imgInfo === false) {
        throw new Exception('File is not a valid image.');
    }

    $bytes = file_get_contents($tmp);
    if ($bytes === false) {
        throw new Exception('Failed to read uploaded file.');
    }

    // Save locally if a directory is specified
    if ($localDir !== '') {
        $ext  = $mimeToExt[$mime];
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!is_dir($localDir)) {
            @mkdir($localDir, 0775, true);
        }
        if (!move_uploaded_file($tmp, rtrim($localDir, '/') . '/' . $name)) {
            throw new Exception('Failed to save uploaded file locally.');
        }
    }

    return 'b64:' . base64_encode($bytes);
}

/**
 * Return an <img>-ready src attribute from a stored image value.
 * Handles both legacy filenames and new "b64:..." base64 strings.
 * Detects the correct MIME type from the raw bytes for b64: values.
 */
function image_display_src(?string $image, string $legacyDir = 'admin/item'): string {
    if (!$image || $image === '') {
        return '/assets/img/placeholder.svg';
    }
    if (strncmp($image, 'b64:', 4) === 0) {
        $raw = base64_decode(substr($image, 4), true);
        if ($raw === false || strlen($raw) < 8) {
            return '/assets/img/placeholder.svg';
        }
        $mime = 'image/jpeg';
        if (str_starts_with($raw, "\x89PNG\r\n\x1a\n")) {
            $mime = 'image/png';
        } elseif (str_starts_with($raw, 'RIFF') && substr($raw, 8, 4) === 'WEBP') {
            $mime = 'image/webp';
        } elseif (str_starts_with($raw, "\xff\xd8\xff")) {
            $mime = 'image/jpeg';
        } elseif (str_starts_with($raw, 'GIF87a') || str_starts_with($raw, 'GIF89a')) {
            $mime = 'image/gif';
        }
        return 'data:' . $mime . ';base64,' . substr($image, 4);
    }
    return upload_web($legacyDir, $image);
}

/**
 * Return a proxy URL for product/rent-item images stored as b64: in Firebase.
 * Falls back to image_display_src() for legacy filenames or external URLs.
 * Keeps the HTML response small — the browser fetches the image separately.
 */
function product_image_url(?string $image, string $id, string $table = 'products'): string {
    if (!$image || $image === '') {
        return '/assets/img/placeholder.svg';
    }
    if (str_starts_with($image, 'b64:')) {
        return '/user/product_image.php?id=' . rawurlencode($id) . '&table=' . rawurlencode($table);
    }
    return image_display_src($image);
}

/* ---------- session cart ---------- */
function items_html($items): string {
    if (!is_array($items) || empty($items)) {
        return '<span class="muted">No items</span>';
    }
    $parts = [];
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $name = (string)($it['name'] ?? 'Item');
        $qty  = (int)($it['qty'] ?? $it['quantity'] ?? 1);
        $parts[] = e($name) . ' <span class="muted">&times;' . $qty . '</span>';
    }
    return $parts ? implode(', ', $parts) : '<span class="muted">No items</span>';
}
function get_cart(): array {
    return $_SESSION['cart'] ?? [];
}
function set_cart(array $cart): void {
    $_SESSION['cart'] = $cart;
}
function cart_count(): int {
    $n = 0;
    foreach (get_cart() as $item) {
        $n += (int)($item['qty'] ?? 0);
    }
    return $n;
}
function cart_total(): float {
    $t = 0.0;
    foreach (get_cart() as $item) {
        $t += (float)($item['price'] ?? 0) * (int)($item['qty'] ?? 0);
    }
    return $t;
}

/* ---------- stock operations ---------- */
function decrement_rent_stock(firebaseRDB $db, string $itemId, int $qty, ?int $currentStock = null): void {
    if ($currentStock === null) {
        $row = $db->retrieve('/rent_items/' . $itemId);
        if (!is_array($row) || !isset($row['quantity'])) {
            return;
        }
        $currentStock = (int)$row['quantity'];
    }
    $new = max(0, $currentStock - $qty);
    $db->update('/rent_items', $itemId, ['quantity' => $new]);
    cache_file_forget('model_raw_rent_items');
}
function restore_rent_stock(firebaseRDB $db, string $itemId, int $qty, ?int $currentStock = null): void {
    if ($currentStock === null) {
        $row = $db->retrieve('/rent_items/' . $itemId);
        $currentStock = (is_array($row) && isset($row['quantity'])) ? (int)$row['quantity'] : 0;
    }
    $db->update('/rent_items', $itemId, ['quantity' => $currentStock + $qty]);
    cache_file_forget('model_raw_rent_items');
}

/* ---------- status helpers ---------- */
function order_status_label(string $status): array {
    $map = [
        'pending'            => ['Pending',     'badge--warn'],
        'accepted'           => ['Accepted',    'badge--info'],
        'preparing'          => ['Preparing',   'badge--info'],
        'ready'              => ['Ready',       'badge--gold'],
        'done'               => ['Completed',   'badge--ok'],
        'cancelled'          => ['Cancelled',   'badge--muted'],
        'cashier_cancelled'  => ['Cancelled',   'badge--muted'],
    ];
    return $map[$status] ?? [ucfirst($status), 'badge--muted'];
}
function booking_status_label(string $status): array {
    $map = [
        'pending'   => ['Pending',   'badge--warn'],
        'accepted'  => ['Approved',  'badge--info'],
        'rejected'  => ['Rejected',  'badge--muted'],
        'returned'  => ['Returned',  'badge--ok'],
        'cancelled' => ['Cancelled', 'badge--muted'],
    ];
    return $map[$status] ?? [ucfirst($status), 'badge--muted'];
}
function payment_status_label(string $status): array {
    $map = [
        'pending_verification'  => ['Verifying',  'badge--warn'],
        'paid'                  => ['Paid',       'badge--ok'],
        'unpaid'                => ['Unpaid',     'badge--danger'],
        'no_payment_required'   => ['At counter', 'badge--muted'],
    ];
    return $map[$status] ?? [ucfirst($status), 'badge--muted'];
}
function payment_method_label(string $method): array {
    $map = [
        'gcash'   => ['GCash',   'badge--blue'],
        'counter' => ['Counter', 'badge--muted'],
    ];
    return $map[$method] ?? [ucfirst($method), 'badge--muted'];
}

/* ---------- input ---------- */
function post(string $key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function is_ajax_request(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/* ---------- local-memory cache (P1) ----------
 * Simple per-request cache to avoid re-fetching the same Firebase nodes on a
 * single page load (e.g. an admin dashboard that reads /orders, /bookings,
 * /products, /rent_items). Lives in a global for the duration of the request. */
function cache_set(string $key, $data, int $ttl = 30): void {
    global $__cache;
    $__cache[$key] = ['data' => $data, 'expires' => time() + $ttl];
}
function cache_remember(string $key, int $ttl, callable $loader) {
    global $__cache;
    if (isset($__cache[$key]) && $__cache[$key]['expires'] > time()) {
        return $__cache[$key]['data'];
    }
    $data = $loader();
    cache_set($key, $data, $ttl);
    return $data;
}

/* ---------- cross-request file cache (P0) ----------
 * Persists data across requests with a TTL. Useful for slow Firebase reads
 * that are acceptable to serve slightly stale (settings, dashboard charts).
 * Stored in sys_get_temp_dir() to avoid permission issues. */
function cache_file_get(string $key, int $ttl, callable $loader) {
    $dir = sys_get_temp_dir() . '/crnp_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . md5($key) . '.cache';
    if (is_file($file) && (time() - filemtime($file)) < $ttl) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            return unserialize($raw);
        }
    }
    $data = $loader();
    @file_put_contents($file, serialize($data), LOCK_EX);
    return $data;
}
function cache_file_forget(string $key): void {
    $dir = sys_get_temp_dir() . '/crnp_cache';
    $file = $dir . '/' . md5($key) . '.cache';
    if (is_file($file)) {
        @unlink($file);
    }
}
function cache_file_clear_all(): void {
    $dir = sys_get_temp_dir() . '/crnp_cache';
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*.cache') ?: [] as $f) {
        @unlink($f);
    }
}

/* ---------- business settings ----------
 * Cached across requests for 300s (5 min). Stale reads are acceptable for
 * business info; admin can manually clear by saving in Settings. */
function get_settings(): array {
    return cache_file_get('business_settings', 300, function () {
        static $cache = null;
        if ($cache !== null) return $cache;
        $defaults = [
            'business_name'   => BRAND_NAME,
            'tagline'         => BRAND_TAGLINE,
            'address'         => 'Mabolo, Iloilo City Proper, Iloilo City, Philippines',
            'phone'           => '+63 (033) 320-0000',
            'hours'           => 'Tue–Sun · 11:00 AM – 10:00 PM (Closed Mondays)',
            'facebook_url'    => '',
            'instagram_url'   => '',
            'support_email'   => '',
            'hero_title'      => 'Your table is waiting.',
            'hero_subtitle'   => "Order ahead for pickup, reserve a table, or book equipment for your next celebration — all from one account.",
            'about_headline'  => 'Your trusted partner for events and celebrations.',
            'about_body'      => "From everyday meals to special gatherings, we bring quality food and reliable rental equipment to every table we serve in Iloilo City.",
            'about_stat1_num' => '10+', 'about_stat1_lbl' => 'Years Experience',
            'about_stat2_num' => '500+', 'about_stat2_lbl' => 'Events Served',
            'about_stat3_num' => '100%', 'about_stat3_lbl' => 'Satisfaction',
            'gcash_number'    => '0917 000 0000',
            'gcash_qr'        => '',
        ];
        try {
            $db = getDB();
            $s = $db->retrieve('/settings');
            $cache = is_array($s) ? array_merge($defaults, $s) : $defaults;
        } catch (Throwable $e) {
            $cache = $defaults;
        }
        return $cache;
    });
}

/* ---------- GCash payment info ---------- */
/**
 * GCash payment info shown when the customer picks GCash.
 * Returns an empty string when no number or QR is configured.
 */
function gcash_payment_info_html(): string {
    $s   = get_settings();
    $num = trim((string)($s['gcash_number'] ?? ''));
    $qr  = trim((string)($s['gcash_qr'] ?? ''));
    if ($num === '' && $qr === '') return '';
    $qrSrc = $qr !== '' ? image_display_src($qr, 'settings') : '';
    $h  = '<div class="gcash-info" style="display:block;margin:10px 0 14px;padding:14px 16px;background:var(--surface-2);border:1px solid var(--line-2);border-radius:var(--radius-sm);text-align:center">';
    if ($qrSrc !== '') {
        $h .= '<img src="' . e($qrSrc) . '" alt="GCash QR code" style="max-width:170px;width:100%;height:auto;border-radius:8px;margin:0 auto 10px;background:#fff;padding:6px;display:block">';
    }
    if ($num !== '') {
        $h .= '<div style="font-size:13px;color:var(--muted)">Send your GCash payment to</div>';
        $h .= '<div style="font-family:var(--sans);font-size:1.3rem;font-weight:700;color:#16a34a;letter-spacing:.03em">' . e($num) . '</div>';
    }
    $h .= '</div>';
    return $h;
}

/* ---------- order tracker stepper ---------- */
function order_tracker_html(string $status): string {
    $steps = [
        'pending'   => ['Pending', 0],
        'accepted'  => ['Accepted', 1],
        'preparing' => ['Preparing', 2],
        'ready'     => ['Ready', 3],
        'done'      => 'done',
    ];
    $cancelled = in_array($status, ['cancelled', 'cashier_cancelled'], true);
    if ($cancelled) {
        return '<div class="badge badge--muted">Cancelled</div>';
    }
    $order = ['pending','accepted','preparing','ready','done'];
    $currentIdx = array_search($status, $order, true);
    if ($currentIdx === false) $currentIdx = 0;
    $labels = ['Pending', 'Accepted', 'Preparing', 'Ready', 'Done'];
    $html = '<div class="tracker" role="list">';
    foreach ($labels as $i => $label) {
        $cls = '';
        if ($i < $currentIdx) $cls = 'tracker__step--done';
        elseif ($i === $currentIdx) $cls = 'tracker__step--current';
        $icon = $i < $currentIdx ? '✓' : ($i + 1);
        $html .= '<div class="tracker__step ' . $cls . '" role="listitem">';
        $html .= '<span class="tracker__dot">' . $icon . '</span>';
        $html .= '<span class="tracker__label">' . e($label) . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
