<?php

namespace App\Enums;

enum ShiftType: string
{
    case Fixed = 'fixed';
    case Flexible = 'flexible';

    public function label(): string
    {
        return match ($this) {
            ShiftType::Fixed => 'Fixed',
            ShiftType::Flexible => 'Flexible',
        };
    }
}
