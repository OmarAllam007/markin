<?php

namespace App\Enums;

enum PunchType: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';

    public function label(): string
    {
        return match ($this) {
            PunchType::CheckIn => 'Check In',
            PunchType::CheckOut => 'Check Out',
        };
    }
}
