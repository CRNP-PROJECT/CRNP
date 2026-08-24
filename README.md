<p align="center">
  <img src="assets/img/logo.png" alt="CRATES N' PLATES logo" width="140">
</p>

<h1 align="center">CRATES N' PLATES — Online Management System</h1>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white" alt="PHP 8.2">
  <img src="https://img.shields.io/badge/Database-Firebase%20RTDB-FFCA28?logo=firebase&logoColor=black" alt="Firebase RTDB">
  <img src="https://img.shields.io/badge/Hosting-Render-46E3B7" alt="Render">
</p>

One web application that runs **Crates N' Plates Diner** online and in-store: customers order food ahead or book rental items from their phones, cashiers run the counter and verify GCash payments, kitchen staff work from a live order display, and the owner manages the menu, inventory, staff accounts, business settings, and sales reports — all from a browser, on any device.

There is nothing to install for end users. Everyone uses the same responsive web app through a link.

---

## Table of Contents

1. [Features](#features)
2. [Technology](#technology)
3. [Deploying to Render](#deploying-to-render)
4. [First-Run Setup](#first-run-setup)
5. [Configuration Reference](#configuration-reference)
6. [Local Development](#local-development)
7. [Maintenance & Security](#maintenance--security)
8. [Troubleshooting](#troubleshooting)
9. [Project Structure](#project-structure)

## Features

**For customers**
- Browse the menu with photos and search; add items to a cart and check out
- Pay via GCash or at the counter; receive email receipts
- Reserve rental equipment with date/time bookings
- Track order status live (pending → preparing → ready → completed) and review history

**For cashiers**
- Point-of-sale console for walk-in orders
- Approve/reject rental bookings, verify GCash proof-of-payment photos, mark items returned
- Printable receipts and an archive of completed/cancelled orders

**For kitchen staff**
- Live kitchen display showing incoming orders
- Forward-only status workflow (accept → preparing → ready) so orders can't skip steps

**For the administrator**
- Dashboard with KPIs, 7-day sales trend, peak-hour chart, top products
- Menu (product) and rental inventory management
- Staff account management for cashier and kitchen roles
- Editable business settings: hours, GCash number & QR code, hero content, About page
- Sales reports with calendar date filtering, plus full order/booking archives

### Permissions Matrix

| Feature | Customer | Cashier | Kitchen | Admin |
|---|:---:|:---:|:---:|:---:|
| Browse menu / order ahead / rent equipment | ✅ | — | — | — |
| Cart + checkout (GCash or pay at counter) | ✅ | — | — | — |
| Book rental items & cancel pending bookings | ✅ | — | — | — |
| Track own orders / view receipts | ✅ | — | — | — |
| Edit own profile + upload avatar | ✅ | — | — | — |
| Order console (accept → preparing → ready) | — | ✅ | ✅ | — |
| Walk-in POS orders | — | ✅ | — | — |
| Approve/reject bookings, verify GCash, mark returned | — | ✅ | — | — |
| Manual walk-in rental bookings | — | ✅ | — | — |
| Print / reprint receipts | — | ✅ | — | — |
| Kitchen display + status workflow | — | — | ✅ | — |
| Product & rental inventory CRUD | — | — | — | ✅ |
| Dashboard analytics + sales reports | — | — | — | ✅ |
| Order & booking history (search / date filter) | — | ✅¹ | ✅¹ | ✅ |
| Staff account management | — | — | — | ✅ |
| Business settings (CMS) | — | — | — | ✅ |

¹ Cashier/Kitchen history pages are read-only archives of their own workflow.

Access is enforced per page by role guards (`includes/auth.php`). Each role uses its own session cookie (`SESS_USER`, `SESS_CASHIER`, `SESS_KITCHEN`, `SESS_ADMIN`), so different roles can be signed in side-by-side in one browser.

## Technology

| Layer | Technology |
|---|---|
| Frontend | Server-rendered PHP pages, vanilla HTML/CSS/JS, mobile-first |
| Backend | PHP 8.2 (procedural pages + lightweight OOP models) |
| Database | Firebase Realtime Database via its REST API (`firebaseRDB.php`) |
| Auth | Email + password with OTP verification, optional Google Sign-In, bcrypt hashing |
| Email | PHPMailer over Gmail SMTP (STARTTLS, port 587) |
| Hosting | Render (Docker runtime, `php:8.2-apache`) |

No build step, no separate API server: PHP serves both the UI and the data layer, which talks to Firebase over REST. A file-based cache keeps dashboard reads fast.

## Deploying to Render

The repository ships with two files that make deployment nearly automatic:

- **`render.yaml`** — Render Blueprint describing the web service (free plan, health checks, environment variables)
- **`Dockerfile`** — packages the PHP app with Apache

> **Note (free plan).** The free instance sleeps after ~15 minutes idle; the first visitor after a lull waits about a minute. Uploaded images (avatars, GCash proofs) are stored on the instance's ephemeral disk and are lost on every redeploy/restart. Upgrade the service plan and attach a disk mounted at `/var/www/html/uploads` when the restaurant goes live for real.

### Step-by-step

1. **Push this repository to GitHub.**

2. **Create the Blueprint.** Render Dashboard → **New → Blueprint** → select the repo → **Apply**. Render reads `render.yaml` and prompts for the secret variables marked `sync: false`.

3. **Fill in the environment variables** when prompted (values explained in [Configuration Reference](#configuration-reference)):
   - `FIREBASE_URL` — copy **verbatim** from Firebase Console → Realtime Database (see the regional-URL warning below)
   - `SMTP_USER` / `SMTP_PASS` — Gmail address and a 16-character App Password ([create one here](https://myaccount.google.com/apppasswords); requires 2-Step Verification)
   - `MAIL_FROM` — optional; defaults to `SMTP_USER`
   - `GOOGLE_CLIENT_ID` — optional OAuth client ID enabling customer Google sign-in
   - `FIREBASE_CREDENTIALS` — full contents of a Firebase service-account JSON key (steps below)

4. **Create the Firebase service-account key** (one time):
   - Firebase Console → ⚙️ **Project settings** → **Service accounts**
   - **Generate new private key** → a JSON file downloads
   - Open it, copy *everything* (including the outer `{ }`), paste into Render's `FIREBASE_CREDENTIALS`

5. **Add your Render URL to Google sign-in** (only if using Google login):
   - [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials) → open the OAuth 2.0 Client ID
   - Under **Authorized JavaScript origins**, add `https://YOUR-SERVICE.onrender.com` (no trailing slash)

6. **Lock down the database.** Firebase Console → **Realtime Database → Rules** → replace with:
   ```json
   { "rules": { ".read": "auth != null", ".write": "auth != null" } }
   ```
   Publish **after** step 4 is complete, otherwise the server loses database access.

> **Regional URL warning.** Databases created outside US-central live on a `*.firebasedatabase.app` domain. Always copy the URL shown above your data tree in Firebase Console — pointing at a `.firebaseio.com` address makes every request fail with *"Database lives in a different region."*

## First-Run Setup

After the first successful deploy:

1. Open `https://YOUR-SERVICE.onrender.com/admin/signup.php` and create the first administrator.
   The page disables itself once an admin exists — then **delete `admin/signup.php` from the server/repo** as good practice.
2. Sign in as admin → **Staff** → create cashier and kitchen accounts.
3. **Settings** → fill in business info, opening hours, GCash number and QR image.
4. **Products / Rent Items** → populate the menu and rental inventory.
5. Customer self-service: `/user/signup.php` (email + OTP verification) or Google sign-in if configured.

## Configuration Reference

All configuration is environment-based — nothing sensitive is stored in code.

| Variable | Required | Description |
|---|:---:|---|
| `FIREBASE_URL` | yes | Realtime Database URL, copied verbatim from Firebase Console. Regional databases use `*.firebasedatabase.app`. |
| `SMTP_USER` | yes | Gmail account that sends OTP, order, and receipt emails |
| `SMTP_PASS` | yes | 16-character Gmail App Password (never the login password) |
| `MAIL_FROM` | no | Outgoing "from" address; defaults to `SMTP_USER` |
| `GOOGLE_CLIENT_ID` | no | OAuth client ID; when set, the customer login page shows a Google button |
| `FIREBASE_CREDENTIALS` | prod | Full service-account JSON key. Signs every database request; required once rules require `auth != null`. Treat as a root secret. |
| `DEV_MODE` | no | `1` shows OTPs on screen when SMTP is down. Development only — never enable in production. |

On Render these live in the service's **Environment** tab. For local development they go in a `.env` file at the project root (git-ignored). Note: `.env` values are parsed line-by-line, so keep the `FIREBASE_CREDENTIALS` JSON on a single line locally.

## Local Development

Requirements: PHP 8.0+ with `curl` and `fileinfo` extensions, Apache (e.g. XAMPP), a Firebase project, a Gmail account.

1. Point Apache's DocumentRoot **at this folder** — internal links assume the app is served from `/`.
2. Create `.env` at the project root:
   ```ini
   FIREBASE_URL="https://your-db-default-rtdb.asia-southeast1.firebasedatabase.app"
   SMTP_USER="your@gmail.com"
   SMTP_PASS="16-char-app-password"
   MAIL_FROM="your@gmail.com"
   GOOGLE_CLIENT_ID=""
   DEV_MODE="0"
   ```
3. Start Apache and open the site. No build step, no migrations.

## Maintenance & Security

| Cadence | Task | How |
|---|---|---|
| Immediately if leaked | Rotate any exposed credential | Gmail App Passwords page or Firebase Console → Service accounts → Keys → delete old, create new, update Render env var |
| Quarterly | Rotate Gmail App Password | Same procedure; update `SMTP_PASS` on Render |
| Quarterly | Rotate service-account key | Delete old key in Firebase Console → generate new → update `FIREBASE_CREDENTIALS` on Render |
| Weekly | Back up data | Firebase Console → Realtime Database → ⋮ → **Export JSON**; store off-site |
| After each deploy | Refresh any open tabs | Deployments reset sessions; stale pages show *"Security token expired"* until reloaded |

Security posture already built in: bcrypt password hashing, per-session CSRF tokens on every form, rate-limited logins, hardened session cookies (secure flags auto-enable under HTTPS), security headers on every response, database access locked behind service-account authentication, and uploads directory hardened against script execution.

Known limitation of the free plan: uploaded files are ephemeral (see note under [Deploying to Render](#deploying-to-render)).

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| *"Database lives in a different region"* in logs; logins fail | `FIREBASE_URL` uses `.firebaseio.com` but DB is regional | Copy exact URL from Firebase Console → Realtime Database |
| *"Access blocked: Authorization Error"* on Google sign-in | Render URL missing from OAuth client origins | Add `https://…onrender.com` under Authorized JavaScript origins, wait ~5 min |
| *"Could not sign you in with Google"* repeatedly | Server-side database write failed | Render Logs → search `[firebaseRDB]`; usually credentials or rules mismatch |
| *"Security token expired"* after submitting a form | Page was open across a redeploy; session reset | Reload the page and retry |
| Site slow on first visit after a quiet period | Free instance woke from spin-down | Expected on free plan; upgrade plan to remove |
| OTP email not arriving | Wrong/rotated App Password | Verify `SMTP_PASS`; check spam folder |

For anything else, check **Render → Logs** first: database errors are logged with a `[firebaseRDB]` prefix describing the exact cause.

## Project Structure

```
CRNP/
├── admin/                  # Admin console (dashboard, products, bookings,
│                           #   reports, staff, settings, history)
├── cashier/                # POS console (walk-in orders, booking queue,
│                           #   GCash verification, receipts, manual bookings)
├── kitchen/                # Kitchen display + forward-only status workflow
├── user/                   # Customer app (shop, cart, checkout, bookings,
│                           #   orders, profile, auth incl. OTP + Google)
├── app/
│   ├── Core/Model.php      # ActiveRecord-style base model
│   └── Models/             # Order, Booking, Product, RentItem, Staff
├── includes/               # Auth guards, helpers, layout shell
│   ├── auth.php            #   require_user/cashier/kitchen/admin
│   ├── functions.php       #   e/redirect/money/csrf/rate_limit/upload/cache
│   └── header.php          #   role-aware nav + theme toggle
├── assets/                 # CSS (light/dark themes), JS, images, logo
├── PHPMailer/              # Vendored PHPMailer (Gmail SMTP)
├── uploads/                # User uploads (avatars, GCash proofs)
├── firebaseRDB.php         # Authenticated cURL wrapper over Firebase REST
├── config.php              # .env loader, session hardening, constants
├── init.php                # Bootstrap + PSR-4 autoloader
├── mailer.php              # OTP, order & booking receipt emails
├── render.yaml             # Render Blueprint (service definition)
├── Dockerfile              # php:8.2-apache container image
└── tests/smoke_token.php   # Offline checks for database authentication
```

---

*Developed as a capstone project for Crates N' Plates Diner.*
