<?php
/**
 * product_image.php — serves product/rent-item images from Firebase b64 data.
 * ?id=KEY&table=products (default) or table=rent_items
 * Uses local file cache so Firebase is only hit once per image.
 */
require_once __DIR__ . '/../init.php';

$id    = trim((string) ($_GET['id'] ?? ''));
$table = trim((string) ($_GET['table'] ?? 'products'));

if ($id === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $id)) {
    http_response_code(400);
    exit;
}

if (!in_array($table, ['products', 'rent_items'], true)) {
    $table = 'products';
}

$cacheDir = UPLOAD_ROOT . '/cache/' . $table;
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$cacheFile = $cacheDir . '/' . $id . '.img';

/* If cached, serve it with 30-day cache. */
if (is_file($cacheFile) && filesize($cacheFile) > 0) {
    $meta = @unserialize(file_get_contents($cacheFile . '.meta'));
    $mime = $meta['mime'] ?? 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=2592000, immutable');
    header('Content-Length: ' . filesize($cacheFile));
    readfile($cacheFile);
    exit;
}

/* Fetch from Firebase. */
$db   = getDB();
$data = $db->retrieve('/' . $table . '/' . $id);
if (!is_array($data)) {
    http_response_code(404);
    exit;
}

$image = $data['image'] ?? '';
if (!str_starts_with($image, 'b64:')) {
    http_response_code(404);
    exit;
}

$raw = @base64_decode(substr($image, 4), true);
if ($raw === false || strlen($raw) < 8) {
    http_response_code(404);
    exit;
}

/* Detect MIME. */
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

/* Cache to disk. */
file_put_contents($cacheFile, $raw);
file_put_contents($cacheFile . '.meta', serialize(['mime' => $mime]));

/* Serve. */
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=2592000, immutable');
header('Content-Length: ' . strlen($raw));
echo $raw;
