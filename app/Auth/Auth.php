<?php

namespace App\Auth;

use App\Support\Flash;
use App\Support\Output;

/**
 * Auth — session guards & current-user helpers for each role.
 */
class Auth {

    public static function requireUser(): void {
        if (empty($_SESSION['user_id'])) {
            Flash::add('Please sign in to continue.', 'warn');
            Output::redirect('/user/login.php');
        }
    }

    public static function requireCashier(): void {
        if (empty($_SESSION['cashier_email'])) {
            Flash::add('Cashier sign-in required.', 'warn');
            Output::redirect('/cashier/login.php');
        }
    }

    public static function requireKitchen(): void {
        if (empty($_SESSION['kitchen_email'])) {
            Flash::add('Kitchen sign-in required.', 'warn');
            Output::redirect('/kitchen/login.php');
        }
    }

    public static function requireAdmin(): void {
        if (empty($_SESSION['admin_email'])) {
            Flash::add('Admin sign-in required.', 'warn');
            Output::redirect('/admin/login.php');
        }
    }

    public static function userName(): string {
        return $_SESSION['user_name'] ?? '';
    }

    public static function userEmail(): string {
        return $_SESSION['user_email'] ?? '';
    }

    public static function userImage(): string {
        return $_SESSION['user_image'] ?? '';
    }

    public static function activeRole(): string {
        if (!empty($_SESSION['admin_email']))    return 'admin';
        if (!empty($_SESSION['cashier_email']))  return 'cashier';
        if (!empty($_SESSION['kitchen_email']))  return 'kitchen';
        if (!empty($_SESSION['user_id']))        return 'customer';
        return 'guest';
    }
}
