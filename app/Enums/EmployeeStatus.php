<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case OnLeave = 'on_leave';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            EmployeeStatus::Active => 'Active',
            EmployeeStatus::Inactive => 'Inactive',
            EmployeeStatus::OnLeave => 'On Leave',
            EmployeeStatus::Terminated => 'Terminated',
        };
    }

    public function color(): string
    {
        return match ($this) {
            EmployeeStatus::Active => 'success',
            EmployeeStatus::Inactive => 'warning',
            EmployeeStatus::OnLeave => 'info',
            EmployeeStatus::Terminated => 'danger',
        };
    }
}
