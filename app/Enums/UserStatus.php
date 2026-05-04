<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            UserStatus::Active => 'Active',
            UserStatus::Inactive => 'Inactive',
            UserStatus::Suspended => 'Suspended',
        };
    }

    public function color(): string
    {
        return match ($this) {
            UserStatus::Active => 'success',
            UserStatus::Inactive => 'warning',
            UserStatus::Suspended => 'danger',
        };
    }
}
