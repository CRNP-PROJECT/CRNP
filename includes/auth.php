<?php
/**
 * auth.php — session guards & current-user helpers.
 * Assumes the PHP app is deployed at the web root (leading-slash URLs).
 */

function require_user(): void {
    if (empty($_SESSION['user_id'])) {
        flash('Please sign in to continue.', 'warn');
        redirect('/user/login.php');
    }
}
function require_cashier(): void {
    if (empty($_SESSION['cashier_email'])) {
        flash('Cashier sign-in required.', 'warn');
        redirect('/cashier/login.php');
    }
}
function require_kitchen(): void {
    if (empty($_SESSION['kitchen_email'])) {
        flash('Kitchen sign-in required.', 'warn');
        redirect('/kitchen/login.php');
    }
}
function require_admin(): void {
    if (empty($_SESSION['admin_email'])) {
        flash('Admin sign-in required.', 'warn');
        redirect('/admin/login.php');
    }
}

function user_name(): string {
    return $_SESSION['user_name'] ?? '';
}
function user_email(): string {
    return $_SESSION['user_email'] ?? '';
}
function user_image(): string {
    return $_SESSION['user_image'] ?? '';
}

/** Detect the active role for nav rendering. */
function active_role(): string {
    if (!empty($_SESSION['admin_email']))    return 'admin';
    if (!empty($_SESSION['cashier_email']))  return 'cashier';
    if (!empty($_SESSION['kitchen_email']))  return 'kitchen';
    if (!empty($_SESSION['user_id']))        return 'customer';
    return 'guest';
}
