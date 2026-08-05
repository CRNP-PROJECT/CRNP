<?php

namespace App\Controllers\User;

/**
 * LogoutController — ends the customer session and returns to the login screen.
 */
class LogoutController {

    public static function render(): void {
        // Wipe any in-memory session state, then destroy the server-side session.
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();

        redirect('/user/login.php');
    }
}
