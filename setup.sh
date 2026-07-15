#!/usr/bin/env bash
#
# CRATES N' PLATES — Restaurant Management Suite
# Seamless setup script for the PHP application.
#
# Usage:
#   chmod +x setup.sh && ./setup.sh
#
# What this script does:
#   1. Checks prerequisites (PHP 8.1+, cURL, OpenSSL, fileinfo extensions)
#   2. Collects Firebase + Gmail SMTP credentials interactively
#   3. Writes a .env file (sourced by config.php via getenv)
#   4. Creates upload directories with correct permissions
#   5. Runs a connectivity test against Firebase + SMTP
#   6. Prints the URL to visit and next steps
#
# ---------------------------------------------------------------------------

# Bail if someone runs this in PowerShell — only Git Bash (MINGW/MSYS) works.
if [ -z "${BASH_VERSION:-}" ]; then
    echo "This script requires Bash. Open Git Bash (not PowerShell/cmd) and run:"
    echo "  cd $(dirname "$0") && bash setup.sh"
    exit 1
fi

set -euo pipefail

# ---- pretty output ----------------------------------------------------------
BOLD=$'\033[1m'
DIM=$'\033[2m'
GOLD=$'\033[33m'
GREEN=$'\033[32m'
RED=$'\033[31m'
BLUE=$'\033[34m'
RESET=$'\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"

# ---- OS detection -----------------------------------------------------------
IS_WINDOWS=false
case "$(uname -s)" in
    MINGW*|MSYS*|CYGWIN*) IS_WINDOWS=true;;
esac

# ---- detect distro (for accurate install hints) -----------------------------
DISTRO="unknown"
PKG_HINT="Install PHP 8.1+ from https://windows.php.net or your OS package manager"
if [ -f /etc/arch-release ]; then
    DISTRO="arch"
    PKG_HINT="sudo pacman -S php"
fi

log()   { echo -e "${BLUE}▸${RESET} $*"; }
ok()    { echo -e "${GREEN}✓${RESET} $*"; }
warn()  { echo -e "${GOLD}!${RESET} $*"; }
err()   { echo -e "${RED}✗${RESET} $*" >&2; }
title() { echo -e "\n${BOLD}$*${RESET}"; }

ask() {
    # ask "prompt" "default" -> echoes the user's answer (or default)
    local prompt="$1" default="${2:-}" reply=""
    if [ -n "$default" ]; then
        read -rp "$(echo -e "${BLUE}?${RESET} ${prompt} ${DIM}[${default}]${RESET} ")" reply
        echo "${reply:-$default}"
    else
        read -rp "$(echo -e "${BLUE}?${RESET} ${prompt} ")" reply
        echo "$reply"
    fi
}

ask_secret() {
    # ask_secret "prompt" -> echoes the user's answer (hidden input)
    local prompt="$1" reply=""
    read -rsp "$(echo -e "${BLUE}?${RESET} ${prompt} ")" reply
    echo >&2   # print the trailing newline to stderr so it doesn't pollute stdout
    echo "$reply"
}

# ---- banner -----------------------------------------------------------------
cat <<'BANNER'
  ╔═══════════════════════════════════════════════════════════╗
  ║   CRATES N' PLATES — Restaurant Management Suite          ║
  ║   Seamless Setup                                           ║
  ╚═══════════════════════════════════════════════════════════╝
BANNER

# ---- 1. prerequisites -------------------------------------------------------
title "1/5  Checking prerequisites"

# PHP
if $IS_WINDOWS && ! command -v php &>/dev/null; then
    for p in "$SCRIPT_DIR/../../php/php.exe" "/c/xampp/php/php.exe" "C:/xampp/php/php.exe"; do
        if [ -x "$p" ]; then
            export PATH="$PATH:$(dirname "$p")"
            ok "Found PHP at $p"
            break
        fi
    done
fi
if command -v php &>/dev/null; then
    PHP_VERSION=$(php -r 'echo PHP_VERSION;')
    PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
    PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')
    if [ "$PHP_MAJOR" -ge 8 ] 2>/dev/null; then
        ok "PHP ${PHP_VERSION}"
    else
        err "PHP ${PHP_VERSION} found — PHP 8.1+ is required."
        exit 1
    fi
else
    err "PHP is not installed. Install PHP 8.1+:"
    err "  ${PKG_HINT}"
    exit 1
fi

# Required extensions
MISSING_EXT=()
for ext in curl openssl mbstring fileinfo; do
    if [ "$(php -r "echo extension_loaded('$ext') ? '1' : '0';" 2>/dev/null)" != "1" ]; then
        MISSING_EXT+=("$ext")
    fi
done
if [ ${#MISSING_EXT[@]} -gt 0 ]; then
    err "Missing PHP extensions: ${MISSING_EXT[*]}"
    if [ "$DISTRO" = "arch" ]; then
        err "  Arch: all four extensions are bundled in the php package."
        err "  Check /etc/php/php.ini — uncomment any that are disabled. Then restart php-fpm."
    fi
    exit 1
fi
ok "PHP extensions: curl, openssl, mbstring, fileinfo"

# cURL CLI (used by this script for the connectivity test)
if command -v curl &>/dev/null; then
    ok "curl CLI"
else
    warn "curl CLI not found — the connectivity test will be skipped."
fi

# ---- 2. collect credentials -------------------------------------------------
title "2/5  Collecting credentials"

echo -e "${DIM}Leave a field blank to use the existing .env value (if any) or the config.php fallback.${RESET}\n"

# Load existing .env values if present
FIREBASE_URL_EXISTING=""
SMTP_USER_EXISTING=""
if [ -f "$ENV_FILE" ]; then
    FIREBASE_URL_EXISTING=$(grep -E '^FIREBASE_URL=' "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '"' || true)
    SMTP_USER_EXISTING=$(grep -E '^SMTP_USER=' "$ENV_FILE" 2>/dev/null | cut -d'=' -f2- | tr -d '"' || true)
fi

echo -e "${BOLD}Firebase Realtime Database${RESET}"
echo -e "${DIM}  Find this in Firebase Console → your project → Realtime Database → Data tab.${RESET}"
echo -e "${DIM}  Format: https://<project-id>-default-rtdb.firebaseio.com${RESET}"
if [ -n "$FIREBASE_URL_EXISTING" ]; then
    FIREBASE_URL=$(ask "Firebase URL:" "$FIREBASE_URL_EXISTING")
else
    FIREBASE_URL=$(ask "Firebase URL:")
fi
if [ -z "$FIREBASE_URL" ]; then
    err "Firebase URL is required."
    exit 1
fi
# strip trailing slash
FIREBASE_URL="${FIREBASE_URL%/}"

echo ""
echo -e "${BOLD}Gmail SMTP${RESET}"
echo -e "${DIM}  Use a Gmail account with 2-Step Verification enabled.${RESET}"
echo -e "${DIM}  Create an App Password: Google Account → Security → App passwords.${RESET}"
echo -e "${DIM}  The password is 16 characters, no spaces.${RESET}"
if [ -n "$SMTP_USER_EXISTING" ]; then
    SMTP_USER=$(ask "Gmail address:" "$SMTP_USER_EXISTING")
else
    SMTP_USER=$(ask "Gmail address:")
fi
SMTP_PASS=$(ask_secret "Gmail App Password (16 chars):")
if [ -z "$SMTP_USER" ] || [ -z "$SMTP_PASS" ]; then
    warn "SMTP credentials incomplete — OTP emails will not send until configured."
    warn "You can re-run this script later to add them."
fi

echo ""
echo -e "${BOLD}Optional${RESET}"
MAIL_FROM=$(ask "From email (defaults to Gmail address):" "$SMTP_USER")
echo -e "${DIM}  Google OAuth: create OAuth credentials in Google Cloud Console → APIs & Services → Credentials.${RESET}"
echo -e "${DIM}  The Client ID looks like: 123456789-abc...apps.googleusercontent.com${RESET}"
echo -e "${DIM}  Leave blank to disable Google sign-in (users can still sign up with email + OTP).${RESET}"
GOOGLE_CLIENT_ID_VAL=$(ask "Google OAuth Client ID (optional):")
DEV_MODE=$(ask "Enable DEV_MODE? (shows OTP on screen — for local dev only) y/N:" "N")
if [[ "$DEV_MODE" =~ ^[Yy]$ ]]; then
    DEV_MODE_VAL="1"
else
    DEV_MODE_VAL="0"
fi

# ---- 3. write .env ----------------------------------------------------------
title "3/5  Writing .env"

cat > "$ENV_FILE" <<EOF
# CRATES N' PLATES — environment configuration
# Generated by setup.sh on $(date '+%Y-%m-%d %H:%M:%S')
# This file is sourced by config.php via getenv(). Keep it secret — do NOT commit.

# Firebase Realtime Database URL
FIREBASE_URL="$FIREBASE_URL"

# Gmail SMTP (App Password — 16 chars, no spaces)
SMTP_USER="$SMTP_USER"
SMTP_PASS="$SMTP_PASS"
MAIL_FROM="$MAIL_FROM"

# Google OAuth Client ID (optional — leave blank to disable Google sign-in)
GOOGLE_CLIENT_ID="$GOOGLE_CLIENT_ID_VAL"

# Dev mode: shows OTP on screen when SMTP is down. NEVER enable in production.
DEV_MODE="$DEV_MODE_VAL"
EOF

if ! $IS_WINDOWS; then
    chmod 600 "$ENV_FILE"  # owner read/write only
    ok ".env written (permissions: 600)"
else
    ok ".env written"
fi

# Also create a .htaccess to block web access to .env (defense in depth)
cat > "$SCRIPT_DIR/.env.htaccess" <<'HTACCESS'
# Block web access to .env
<FilesMatch "^\.env">
    Require all denied
</FilesMatch>
HTACCESS
ok ".env web access blocked (.env.htaccess)"

# ---- 4. upload directories --------------------------------------------------
title "4/5  Setting up upload directories"

UPLOAD_DIRS=(
    "$SCRIPT_DIR/uploads"
    "$SCRIPT_DIR/uploads/user"
    "$SCRIPT_DIR/uploads/user/bookings"
    "$SCRIPT_DIR/uploads/user/profile"
    "$SCRIPT_DIR/uploads/admin"
    "$SCRIPT_DIR/uploads/admin/item"
)
for dir in "${UPLOAD_DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
    fi
done
ok "Upload directories created"

# Determine the web server user for ownership (skip on Windows)
WEB_USER=""
if ! $IS_WINDOWS; then
    for candidate in www-data http apache nginx; do
        if id -u "$candidate" &>/dev/null 2>&1; then
            WEB_USER="$candidate"
            break
        fi
    done
fi

if [ "$(id -u)" = "0" ] && [ -n "$WEB_USER" ]; then
    chown -R "$WEB_USER":"$WEB_USER" "$SCRIPT_DIR/uploads" 2>/dev/null || true
    ok "Upload ownership set to ${WEB_USER}:${WEB_USER}"
    warn "If your web server runs as a different user, adjust: sudo chown -R <user>:<group> uploads/"
else
    warn "Not running as root — please set upload ownership manually:"
    if [ -n "$WEB_USER" ]; then
        warn "  sudo chown -R ${WEB_USER}:${WEB_USER} uploads/"
    else
        warn "  sudo chown -R <web-server-user>:<web-server-user> uploads/"
    fi
fi

if ! $IS_WINDOWS; then
    chmod -R 775 "$SCRIPT_DIR/uploads"
    ok "Upload permissions: 775"
fi

# Ensure .htaccess in uploads blocks script execution
if [ ! -f "$SCRIPT_DIR/uploads/.htaccess" ]; then
    cat > "$SCRIPT_DIR/uploads/.htaccess" <<'HTACCESS'
# Prevent execution of PHP/scripts inside the uploads directory.
php_flag engine off
Options -ExecCGI
<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|cgi|sh)$">
    Require all denied
</FilesMatch>
HTACCESS
fi
ok "uploads/.htaccess in place (script execution blocked)"

# ---- 5. connectivity test ---------------------------------------------------
title "5/5  Connectivity test"

if [ -z "$FIREBASE_URL" ]; then
    warn "Skipping connectivity test — no Firebase URL."
else
    log "Testing Firebase Realtime Database..."
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 \
        "${FIREBASE_URL}/.json" 2>/dev/null || echo "000")
    case "$HTTP_CODE" in
        200|401|403)
            ok "Firebase reachable (HTTP ${HTTP_CODE})"
            ;;
        000)
            err "Firebase unreachable — check the URL and your internet connection."
            ;;
        *)
            warn "Firebase returned HTTP ${HTTP_CODE} — verify the URL in Firebase Console."
            ;;
    esac
fi

# PHP syntax check on all files
log "Checking PHP syntax..."
SYNTAX_ERRORS=0
while IFS= read -r f; do
    if ! php -l "$f" &>/dev/null; then
        err "Syntax error in: $f"
        php -l "$f"
        SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
    fi
done < <(find "$SCRIPT_DIR" -name "*.php" -not -path "*/PHPMailer/*")
if [ "$SYNTAX_ERRORS" -eq 0 ]; then
    ok "All PHP files pass syntax check"
else
    err "${SYNTAX_ERRORS} file(s) have syntax errors."
fi

# ---- summary ----------------------------------------------------------------
title "Setup complete!"

cat <<EOF

  ${GREEN}CRATES N' PLATES is configured and ready.${RESET}

  ${BOLD}Next steps:${RESET}

  1. ${BOLD}Serve the app${RESET} with PHP's built-in server (for local testing):
       cd "$SCRIPT_DIR"
       php -S localhost:8000

     Or copy this entire folder to your web host's document root.

  2. ${BOLD}Create your first admin${RESET}:
       Visit  http://localhost:8000/admin/signup.php
       Register, then ${BOLD}delete admin/signup.php${RESET} for production.

  3. ${BOLD}Seed your catalog${RESET}:
       Sign in as admin → Products → add dishes.
       Rent Items → add tables, chairs, skirting.

  4. ${BOLD}Configure business info${RESET}:
       Admin → Settings → set hours, phone, address, social links.

  ${BOLD}Configuration file:${RESET}  $ENV_FILE
  ${BOLD}Documentation:${RESET}       $SCRIPT_DIR/README.md

EOF

if [ "$DEV_MODE_VAL" = "1" ]; then
    echo -e "  ${GOLD}⚠ DEV_MODE is ON.${RESET} OTP codes will be shown on screen if SMTP fails."
    echo -e "  ${GOLD}  Disable before going live: set DEV_MODE=\"0\" in .env${RESET}\n"
fi
