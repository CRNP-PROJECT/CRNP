<?php

namespace App\Controllers\Admin;

/**
 * LogoutController — End the admin session.
 */
class LogoutController {

    public static function render(): void {
        // Wipe in-memory state, expire the session cookie, then destroy server-side.
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();

        redirect('/admin/login.php');
    }
}
