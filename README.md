# CRATES N' PLATES — Online Management System

A full-stack restaurant management web application for Crates N' Plates: an online menu/shop with cart & checkout, walk-in point-of-sale, rental reservations, kitchen order display, inventory, sales analytics and reporting, email notifications, and role-based user management. Web app only — customers, cashiers, kitchen staff, and admins all use the same responsive web app, not separate mobile applications.

## Tech Stack

| Layer      | Technology |
|------------|-----------|
| Frontend   | Server-rendered PHP pages (vanilla HTML/CSS/JS, mobile-first) |
| Backend    | PHP 8+ (procedural pages + lightweight OOP Models) |
| Database   | Firebase Realtime Database (RTDB REST API via `firebaseRDB` cURL wrapper) |
| Styling    | Custom CSS (`assets/css/style.css`) with light/dark themes |
| Email      | PHPMailer (Gmail SMTP, STARTTLS port 587) |
| Auth       | Email + password with OTP verification, Google Identity Services (optional), bcrypt hashing, per-role sessions |
| Charts     | Inline SVG (pie, bar, column, peak-hour charts — no JS chart library) |

## Quick Start (Development)

Requirements: PHP 8.0+ (with `curl` and `fileinfo` extensions) + Apache, e.g. XAMPP; a Firebase Realtime Database project; a Gmail account for SMTP.

```bash
# 1. Place the app at your web root.
#    All internal links use leading slashes (/user/login.php, /assets/...),
#    so the document root must point AT the app folder (set a VirtualHost in
#    httpd.conf or point Apache's DocumentRoot at this directory).

# 2. Create .env at the project root (there is no .env.example; .env is
#    git-ignored and read by config.php). Start from the defaults below:
FIREBASE_URL="https://your-project-default-rtdb.firebaseio.com"
SMTP_USER="atengcornel@gmail.com"
SMTP_PASS="vvwa opfq pnoq nssb"
MAIL_FROM="atengcornel@gmail.com"          # optional; defaults to SMTP_USER
GOOGLE_CLIENT_ID="169153827262-v3jf50qufjq3ikvo8j1t4u1s4qgttc5e.apps.googleusercontent.com"   # optional; leave blank to disable Google sign-in
DEV_MODE="0"          # optional; "1" shows the OTP on screen when SMTP is down
```

- `FIREBASE_URL` — RTDB URL for the `firebaseRDB` client. **Required**; there is no working default.
- `SMTP_USER` / `SMTP_PASS` — Gmail address and 16-character App Password used by PHPMailer to send OTP, order, and booking emails.
- `MAIL_FROM` — optional "from" address; defaults to `SMTP_USER`.
- `GOOGLE_CLIENT_ID` — optional OAuth 2.0 client ID; when set, the customer login page adds a Google sign-in button.
- `DEV_MODE` — dev-only convenience: leaks the OTP on screen when SMTP is not configured. **Never** enable in production.

```bash
# 3. Start Apache in XAMPP and open the app:

# 4. First-run setup:
#    /admin/signup.php   # create the first administrator (page self-disables
#                        # once an admin exists; delete the file in production)
#    /user/signup.php    # customer signup (email OTP verification)
#    Admin -> Staff      # create cashier & kitchen accounts
#    Admin -> Settings   # business info, hours, GCash number/QR
#    Admin -> Products / Rent Items  # populate menu and rental inventory
```

There is no build step and no database migration — data lives directly in Firebase RTDB and writes through on page actions.

## Authentication

- **Customers** sign in at `/user/login.php` with email + password (signup requires a 6-digit OTP emailed via Gmail SMTP, valid 10 minutes) or with Google Identity Services when `GOOGLE_CLIENT_ID` is set. Forgot/reset password flow is available.
- **Admin** — the first administrator is bootstrapped at `/admin/signup.php`. The page checks that no `/admins` record exists and redirects away as soon as one is created.
- **Cashier & Kitchen** accounts are created by an admin under `Admin -> Staff`; passwords are stored as bcrypt hashes in the `/cashiers` and `/kitchen` nodes.
- **Sessions** — each role uses its own cookie (`SESS_USER`, `SESS_CASHIER`, `SESS_KITCHEN`, `SESS_ADMIN`) so different roles can be open side-by-side in one browser. Sessions expire after 24h idle and IDs are regenerated on login.
- **Security** — every POST form carries a per-session CSRF token (`csrf_verify()`), logins are rate-limited, sessions use hardened cookie flags, and every page sends security headers (CSP, `X-Frame-Options`, etc.).

## Environment Variables

| Variable            | Required | Description |
|---------------------|:--------:|-------------|
| `FIREBASE_URL`      | yes      | Firebase Realtime Database URL used by `firebaseRDB` |
| `SMTP_USER`         | yes      | Gmail address for PHPMailer SMTP |
| `SMTP_PASS`         | yes      | 16-character Gmail App Password |
| `MAIL_FROM`         | no       | Outgoing "from" address (defaults to `SMTP_USER`) |
| `GOOGLE_CLIENT_ID`  | no       | OAuth client ID enabling Google sign-in for customers |
| `FIREBASE_CREDENTIALS` | prod     | Full service-account JSON (secret). Signs every RTDB request; required once database rules require `auth != null` |
| `DEV_MODE`          | no       | `1`/`true` surfaces the OTP on screen when SMTP is down (dev only) |

`config.php` loads these from `.env` at the project root via `getenv()`; you may also export them directly on your PHP host.

**Production database rules.** With `FIREBASE_CREDENTIALS` set, publish Realtime
Database rules that deny anonymous access (Firebase Console → Realtime Database
→ Rules):
```json
{ "rules": { ".read": "auth != null", ".write": "auth != null" } }
```
One-time credential setup: Google Cloud Console → IAM & Admin → Service
Accounts → Create (no IAM roles needed) → Keys → Add key → JSON. Paste the
file's contents into the `FIREBASE_CREDENTIALS` env var on your host/Render.
For a local `.env`, put the JSON on a single line — the loader parses
line-by-line, so pretty-printed JSON would truncate at the first newline.

## User Roles & Permissions

| Feature                                        | Customer | Cashier | Kitchen | Admin |
|------------------------------------------------|:--------:|:-------:|:-------:|:-----:|
| Browse menu / order ahead / rent equipment     | ✅       | —       | —       | —     |
| Cart + checkout (GCash or pay at counter)      | ✅       | —       | —       | —     |
| Book rental items & cancel pending bookings    | ✅       | —       | —       | —     |
| Track own orders (status stepper) / view receipts | ✅    | —       | —       | —     |
| Edit own profile + upload avatar               | ✅       | —       | —       | —     |
| Order console (accept → preparing → ready)     | —        | ✅      | ✅      | —     |
| Walk-in POS orders (`/cashier/order_now.php`)  | —        | ✅      | —       | —     |
| Approve / reject bookings, mark returned, verify GCash | — | ✅ | — | — |
| Manual walk-in rental bookings                 | —        | ✅      | —       | —     |
| Print / reprint receipts                       | —        | ✅      | —       | —     |
| Kitchen display + status workflow              | —        | —       | ✅      | —     |
| Product (menu) CRUD                            | —        | —       | —       | ✅    |
| Rent inventory CRUD                            | —        | —       | —       | ✅    |
| Dashboard analytics + sales reports            | —        | —       | —       | ✅    |
| Order & booking history (search / date filter) | —        | ✅¹     | ✅¹     | ✅    |
| Staff account management                       | —        | —       | —       | ✅    |
| Business settings (CMS: hours, GCash, hero)    | —        | —       | —       | ✅    |

¹ Cashier/Kitchen history pages are read-only archives of their own workflow.

Notes:

- Access control is enforced at the page level by auth guards (`require_user()`, `require_cashier()`, `require_kitchen()`, `require_admin()` in `includes/auth.php`) — each area redirects to its own login if not signed in.
- Customers never see staff screens (Dashboard, POS, Bookings queue, Reports); staff never see the customer shop/cart. The shared `includes/header.php` renders a role-specific nav.

## Project Structure

```
CRNP/
├── admin/                  # Admin console
│   ├── index.php          # Dashboard: KPI strip, 7-day sales, pie/peak/top charts
│   ├── products.php       # Menu (product) CRUD
│   ├── rent_items.php     # Rental inventory CRUD
│   ├── bookings.php       # Rent reservations overview (paged)
│   ├── history.php        # Full order + booking archive (search / date filter)
│   ├── reports.php        # Sales report with calendar date picker
│   ├── staff.php          # Manage cashier & kitchen accounts
│   ├── settings.php       # CMS-editable business info, hours, GCash number/QR
│   ├── signup.php         # Bootstrap first admin (self-disables, then remove)
│   └── login.php / logout.php
├── cashier/                # Cashier console
│   ├── index.php          # Orders management console (+ live indicator)
│   ├── order_now.php      # POS / walk-in order creation
│   ├── bookings.php       # Rental bookings queue (approve/reject/return)
│   ├── manual_booking.php # Walk-in rental booking
│   ├── receipt.php / booking_receipt.php   # Printable receipts
│   └── history.php        # Archive of cancelled/completed orders
├── kitchen/                # Kitchen Display
│   ├── index.php          # Active orders + forward-only status workflow
│   └── history.php        # Archive of done orders
├── user/                   # Customer-facing app
│   ├── login.php / signup.php / verify_otp.php / forgot_password.php /
│   │   reset_password.php / google_auth.php / logout.php
│   ├── about.php          # Public About Us page (CMS-driven)
│   ├── products.php       # Shop with search + Buy Now
│   ├── cart.php / checkout.php    # Cart + checkout (GCash / counter)
│   ├── booking.php / booking_receipt.php   # Rental booking + receipt
│   ├── your_orders.php    # Order & booking history (cancel pending)
│   ├── your_profile.php   # Profile edit + avatar upload
│   └── product_image.php  # Proxies b64 images out of Firebase
├── app/
│   ├── Core/Model.php     # ActiveRecord-style base (all/find/where/save/paginate)
│   └── Models/            # Order, Booking, Product, RentItem, Staff
├── includes/
│   ├── auth.php           # require_* guards + current-user helpers
│   ├── functions.php      # helpers: e/redirect/money/csrf/rate_limit/upload/cache/status labels
│   ├── header.php         # layout shell + role-specific nav + theme toggle
│   └── footer.php
├── assets/css|js|img/      # style.css (themes), app.js, logos
├── PHPMailer/              # vendored PHPMailer (Gmail SMTP)
├── uploads/                # user uploads (avatars, booking receipts)
├── firebaseRDB.php         # cURL wrapper over the Firebase REST API
├── db.php                  # getDB() factory
├── config.php              # .env loader, session hardening, per-role cookies, constants
├── init.php                # single bootstrap + PSR-4 autoloader for App\
├── mailer.php              # OTP, order & booking receipt emails
├── index.php               # root redirect to /user/login.php
└── CONVENTIONS.md          # coding conventions (read before editing)
```

## Production Deployment

```bash
# XAMPP / Apache + PHP 8+
# 1. Point the DocumentRoot (or a VirtualHost) at the app folder so URLs
#    resolve from "/" — internal links assume the web root.
# 2. Export the real credentials as environment variables on the host
#    (FIREBASE_URL, SMTP_USER, SMTP_PASS, MAIL_FROM) or keep a .env file.
# 3. Enable the php_curl and php_fileinfo extensions.
# 4. Visit /admin/signup.php to create the first admin, then DELETE the file.
# 5. Keep DEV_MODE off, set display_errors=0 in production, and enable HTTPS
#    (config.php automatically sets cookie_secure when HTTPS is detected).
```

There is no compilation step and no separate API server — PHP serves both the UI and the data layer, which talks to Firebase RTDB over REST (see `firebaseRDB.php`). A per-request and file-based cache (`cache_remember`, `cache_file_get`) keeps Firebase reads fast on dashboards and the storefront.
