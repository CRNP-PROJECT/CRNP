CONVENTIONS — CRATES N' PLATES PHP app (read before editing any role page)
=========================================================================

Bootstrap: every page starts with `require_once __DIR__ . '/../init.php';`
init.php loads: config, firebaseRDB, db (getDB()), functions, auth guards, mailer.

Firebase client: `$db = getDB();` returns a firebaseRDB.
  - $db->retrieve('/products')                 -> array(id=>row) or [] on failure
  - $db->retrieve('/products/'.$id)             -> row array or []
  - $db->insert('/orders', $data)               -> new push key (throws on error)
  - $db->update('/orders', $id, $data)          -> patched array (throws on error)
  - $db->delete('/orders', $id)                 -> true (throws on error)
IMPORTANT: To list items belonging to a user/field, call retrieve('/table') then
filter_by($rows,'user_email',$email) — do NOT rely on Firebase orderBy index rules.

Layout: render header + footer:
  $pageTitle='...'; $activeNav='shop'; $layout='narrow|wide|';  // optional
  require_once __DIR__ . '/../includes/header.php';
  ... page body ...
  require_once __DIR__ . '/../includes/footer.php';

Auth guards (call before any output for protected pages):
  require_user(); require_cashier(); require_kitchen(); require_admin();

Flash messages: flash($msg,$type) with type in ok|warn|danger|info. Shown by header.

Helpers available: e() redirect() now() money() gen_otp() rows()
  filter_by() filter_like() save_upload() upload_web()
  get_cart() set_cart() cart_count() cart_total()
  decrement_product_stock() restore_product_stock()
  decrement_rent_stock() restore_rent_stock()
  order_status_label() booking_status_label() payment_status_label() -> [$label,$badgeClass]
  post($key,$default)

POST handling pattern: detect `if ($_SERVER['REQUEST_METHOD']==='POST')`,
  read with post(), validate, call Firebase, flash()+redirect() to avoid re-submit.

Stock rules (BUSINESS LOGIC):
  - Decrement stock AFTER a successful insert/update (wrap in try, check return).
  - On cancel/reject/return: restore stock using the Firebase KEY stored in the
    items array (never match by name). Check current status first to avoid double-restore.
  - Booking items are stored as { itemId: {name, qty, price, subtotal} }.

Uploads: save_upload($field, UPLOAD_ROOT.'/user/bookings') returns filename;
  display with upload_web('user/bookings', $filename).
  Categories in use: 'user/bookings' (receipts), 'user/profile' (avatars),
  'admin/item' (item images).

CSS classes available (style.css): .card .card--pad .card__head .card__body
  .btn .btn--gold .btn--outline .btn--ghost .btn--danger .btn--ok .btn--sm .btn--lg .btn--block
  .field .input .select .textarea .form-grid .form-grid--2 .form-actions .checkbox-row
  table.tbl inside .table-wrap ; .num .muted td helpers
  .badge .badge--warn .badge--ok .badge--danger .badge--info .badge--gold .badge--muted
  .alert .alert--ok .alert--warn .alert--danger .alert--info
  .grid .grid--products .grid--cards .grid--stat ; .product .product__media .product__body .product__foot
  .stat .stat__label .stat__value ; .hero .eyebrow ; .page-head .page-head__row
  .empty .empty__icon ; .row .row--between .col .muted .t-right .mt-2/4/6 .mb-0/2/4 .grow .divider
  For auth pages use shell: <div class="auth"><aside class="auth__aside">...</aside>
    <main class="auth__main"><div class="auth__card card card--pad-lg">...</div></main></div>
    and DO NOT use the standard header/footer for auth screens (build a full HTML doc).

Timezone is Asia/Manila everywhere (set in config).

Deploy assumption: app served at web root, so links use leading slash: /user/login.php,
/assets/css/style.css, /uploads/...

Google login: frontend posts a JWT to google_auth.php. Decode the payload segment
(base64url) -> json -> {email,name,picture}. Create/update /user record by email,
set $_SESSION['user_id'], 'user_email','user_name','user_image'. Regenerate session id.

Password storage: password_hash($pw, PASSWORD_BCRYPT) on signup; password_verify on login.
Signup flow: insert pending user with otp + otp_expires (now+10min), email OTP via sendOTP(),
redirect to verify_otp.php?email=... ; on verify set email_verified=true.

Always regenerate session id on successful login: session_regenerate_id(true);

## Dark mode & mobile-first (added)

- Theme system: `<html data-theme="dark">` toggles a full dark palette via CSS
  variables (defined in assets/css/style.css under `[data-theme="dark"]`). No
  class on <html> = light (default).
- Persistence: localStorage key `ss-theme` = `'dark'|'light'`.
- No-flash: every page has an inline `<script>` in `<head>` (before the
  stylesheet) that reads localStorage and sets `data-theme` before paint. On
  first visit it respects `prefers-color-scheme: dark`.
- Toggle button: `<button class="theme-toggle" data-theme-toggle>` with sun/moon
  SVGs (icon swap handled by CSS). handler in assets/js/app.js.
  - Logged-in pages: button is in the topbar (includes/header.php).
  - Auth pages: floating button `.theme-toggle--floating` (fixed top-right).
- CSS is mobile-first: base styles target mobile; `@media (min-width: 480/640/769/901px)`
  enhance for larger screens. Nav is a burger dropdown on mobile, inline on desktop.
