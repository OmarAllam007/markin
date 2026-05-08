<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function label(): string
    {
        return match ($this) {
            MaritalStatus::Single => 'Single',
            MaritalStatus::Married => 'Married',
            MaritalStatus::Divorced => 'Divorced',
            MaritalStatus::Widowed => 'Widowed',
        };
    }
}
