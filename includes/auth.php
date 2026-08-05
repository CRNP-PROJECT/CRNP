<?php
/**
 * auth.php — session guards & current-user helpers.
 * Thin wrappers delegating to the App\Auth\Auth class.
 */

use App\Auth\Auth;

function require_user(): void { Auth::requireUser(); }
function require_cashier(): void { Auth::requireCashier(); }
function require_kitchen(): void { Auth::requireKitchen(); }
function require_admin(): void { Auth::requireAdmin(); }

function user_name(): string { return Auth::userName(); }
function user_email(): string { return Auth::userEmail(); }
function user_image(): string { return Auth::userImage(); }
function active_role(): string { return Auth::activeRole(); }
