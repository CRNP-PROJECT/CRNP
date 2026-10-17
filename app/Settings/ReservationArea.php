<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * A reservation area whose seats are capacity-limited in the public occupancy
 * view. Capacities are admin-editable smart defaults — the owner adjusts only
 * what differs from the diner's usual layout.
 */
enum ReservationArea: string
{
    case DineIn = 'dine_in';
    case FunctionRoom = 'function_room';
    case Catering = 'catering';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-in',
            self::FunctionRoom => 'Function room',
            self::Catering => 'Catering',
        };
    }

    /**
     * The smart default pre-filled on the settings form.
     */
    public function defaultCapacity(): int
    {
        return match ($this) {
            self::DineIn => 40,
            self::FunctionRoom => 60,
            self::Catering => 100,
        };
    }
}
