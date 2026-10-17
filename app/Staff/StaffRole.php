<?php

declare(strict_types=1);

namespace App\Staff;

/**
 * A staff member's role. Staff are created by an admin (name + Google email)
 * and never by public signup; `/cashier` and `/admin` are guarded per role.
 */
enum StaffRole: string
{
    case Admin = 'admin';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Cashier => 'Cashier',
        };
    }
}
