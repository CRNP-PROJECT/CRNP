<?php

namespace App\Controllers\User;

/**
 * GoogleAuthController — Google Identity Services callback.
 * Receives a JWT `credential` from the GIS button, decodes the payload,
 * upserts the matching /user record by email, and starts the customer
 * session. Also appends an entry to /google_create_account as a log.
 */
class GoogleAuthController {

    public static function render(): void {
        // Verify CSRF before accepting the JWT posted from the Google Sign-In button.
        csrf_verify();

        // ---------- Resolve credential ----------
        $jwt = (string) post('credential', '');
        if ($jwt === '') {
            flash('Missing Google credential. Please try again.', 'danger');
            redirect('/user/login.php');
        }

        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            flash('Invalid Google credential. Please try again.', 'danger');
            redirect('/user/login.php');
        }

        $payload = json_decode(self::base64url_decode($parts[1]), true);
        if (!is_array($payload)) {
            flash('Could not read Google account info. Please try again.', 'danger');
            redirect('/user/login.php');
        }

        $gEmail   = trim((string) ($payload['email']   ?? ''));
        $gName    = trim((string) ($payload['name']    ?? ''));
        $gPicture = trim((string) ($payload['picture'] ?? ''));

        if (!filter_var($gEmail, FILTER_VALIDATE_EMAIL)) {
            flash('Your Google account did not provide a valid email.', 'danger');
            redirect('/user/login.php');
        }

        // ---------- Upsert /user by email ----------
        $db       = getDB();
        $existing = filter_by(rows($db->retrieve('/user')), 'email', $gEmail);
        $userId   = null;
        $userName = $gName !== '' ? $gName : explode('@', $gEmail)[0];
        $userImg  = $gPicture;

        try {
            if ($existing) {
                $userId = (string) array_key_first($existing);
                $row    = reset($existing);

                $patch = [];
                if ($gName !== '' && ($row['name'] ?? '') !== $gName) {
                    $patch['name'] = $gName;
                }
                if ($gPicture !== '' && ($row['profile_image'] ?? '') !== $gPicture) {
                    $patch['profile_image'] = $gPicture;
                }
                // Promote to verified + provider google if needed.
                if (empty($row['email_verified'])) {
                    $patch['email_verified'] = true;
                }
                if (($row['provider'] ?? '') === '' || $row['provider'] === 'email') {
                    $patch['provider'] = 'google';
                }
                if ($patch) {
                    $db->update('/user', $userId, $patch);
                }
                $userName = $patch['name'] ?? ($row['name'] ?? $userName);
                $userImg  = $patch['profile_image'] ?? ($row['profile_image'] ?? $userImg);
            } else {
                $userId = $db->insert('/user', [
                    'name'           => $userName,
                    'email'          => $gEmail,
                    'email_verified' => true,
                    'provider'       => 'google',
                    'profile_image'  => $gPicture,
                    'password_hash'  => '',
                    'created_at'     => now(),
                ]);
            }
        } catch (Throwable $e) {
            flash('Could not sign you in with Google. Please try again.', 'danger');
            redirect('/user/login.php');
        }

        // ---------- Append to google_create_account log ----------
        try {
            $db->insert('/google_create_account', [
                'email'      => $gEmail,
                'name'       => $gName,
                'picture'    => $gPicture,
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Logging failure is non-fatal; the session still goes ahead.
            error_log('[google_auth] could not append to /google_create_account: ' . $e->getMessage());
        }

        // ---------- Start session ----------
        session_regenerate_id(true);
        $_SESSION['user_id']    = (string) $userId;
        $_SESSION['user_email'] = $gEmail;
        $_SESSION['user_name']  = $userName;
        $_SESSION['user_image'] = $userImg;

        flash('Signed in with Google as ' . $gEmail . '.', 'ok');
        redirect('/user/products.php');
    }

    /**
     * Decode a base64url string (with URL-safe -/_ and missing padding).
     */
    private static function base64url_decode(string $s): string {
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        return base64_decode(strtr($s, '-_', '+/')) ?: '';
    }
}
