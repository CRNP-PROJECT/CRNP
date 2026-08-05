<?php
/**
 * functions.php — shared helpers used across every role.
 *
 * These are thin delegation wrappers: the actual logic lives in the OOP
 * classes under app/ (App\Support\*, App\Auth\Auth, App\Mail\Mailer).
 * Keeping the wrappers lets every existing page keep working unchanged.
 */

use App\Support\Security;
use App\Support\Output;
use App\Support\Flash;
use App\Support\Data;
use App\Support\Upload;
use App\Support\Cart;
use App\Support\Stock;
use App\Support\Status;
use App\Support\Cache;

/* ---------- security ---------- */
function security_headers(): void { Security::headers(); }
function csrf_token(): string { return Security::csrfToken(); }
function csrf_field(): string { return Security::csrfField(); }
function csrf_verify(): void { Security::csrfVerify(); }
function rate_limit(string $key, int $maxAttempts, int $windowSecs): bool { return Security::rateLimit($key, $maxAttempts, $windowSecs); }

/* ---------- output / flow ---------- */
function e($s): string { return Output::escape($s); }
function redirect(string $url): void { Output::redirect($url); }
function now(): string { return Output::now(); }
function money($n): string { return Output::money($n); }
function gen_otp(): string { return Output::genOtp(); }
function items_html($items): string { return Output::itemsHtml($items); }

/* ---------- flash messages ---------- */
function flash(string $message, string $type = 'info'): void { Flash::add($message, $type); }
function get_flashes(): array { return Flash::get(); }

/* ---------- data / filtering / input ---------- */
function rows($data): array { return Data::rows($data); }
function filter_by(array $rows, string $key, $val): array { return Data::filterBy($rows, $key, $val); }
function filter_like(array $rows, string $key, $val): array { return Data::filterLike($rows, $key, $val); }
function post(string $key, $default = '') { return Data::post($key, $default); }
function is_ajax_request(): bool { return Data::isAjaxRequest(); }

/* ---------- file uploads ---------- */
function save_upload(string $field, string $destDir, array $allowed = ['jpg', 'jpeg', 'png', 'webp'], int $maxMB = 5): ?string { return Upload::save($field, $destDir, $allowed, $maxMB); }
function upload_web(string $category, ?string $filename): string { return Upload::web($category, $filename); }
function upload_to_base64(string $field, string $localDir = '', int $maxMB = 5): ?string { return Upload::toBase64($field, $localDir, $maxMB); }
function image_display_src(?string $image, string $legacyDir = 'admin/item'): string { return Upload::displaySrc($image, $legacyDir); }
function product_image_url(?string $image, string $id, string $table = 'products'): string { return Upload::productImageUrl($image, $id, $table); }

/* ---------- session cart ---------- */
function get_cart(): array { return Cart::get(); }
function set_cart(array $cart): void { Cart::set($cart); }
function cart_count(): int { return Cart::count(); }
function cart_total(): float { return Cart::total(); }

/* ---------- stock operations ---------- */
function decrement_rent_stock(firebaseRDB $db, string $itemId, int $qty, ?int $currentStock = null): void { Stock::decrementRent($db, $itemId, $qty, $currentStock); }
function restore_rent_stock(firebaseRDB $db, string $itemId, int $qty, ?int $currentStock = null): void { Stock::restoreRent($db, $itemId, $qty, $currentStock); }

/* ---------- status helpers ---------- */
function order_status_label(string $status): array { return Status::orderLabel($status); }
function booking_status_label(string $status): array { return Status::bookingLabel($status); }
function payment_status_label(string $status): array { return Status::paymentStatusLabel($status); }
function payment_method_label(string $method): array { return Status::paymentMethodLabel($method); }
function order_tracker_html(string $status): string { return Status::orderTrackerHtml($status); }

/* ---------- caching ---------- */
function cache_set(string $key, $data, int $ttl = 30): void { Cache::set($key, $data, $ttl); }
function cache_remember(string $key, int $ttl, callable $loader) { return Cache::remember($key, $ttl, $loader); }
function cache_file_get(string $key, int $ttl, callable $loader) { return Cache::fileGet($key, $ttl, $loader); }
function cache_file_forget(string $key): void { Cache::fileForget($key); }
function cache_file_clear_all(): void { Cache::fileClearAll(); }

/* ---------- business settings ---------- */
function get_settings(): array { return Cache::businessSettings(); }

/**
 * GCash payment info shown when the customer/cashier picks GCash.
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
