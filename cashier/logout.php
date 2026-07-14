<?php
/**
 * cashier/logout.php — end the cashier session.
 */
require_once __DIR__ . '/../init.php';

// Wipe in-memory state, expire the session cookie, then destroy server-side.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

redirect('/cashier/login.php');
