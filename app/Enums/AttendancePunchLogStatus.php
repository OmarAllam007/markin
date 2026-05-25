<?php

namespace App\Enums;

enum AttendancePunchLogStatus: string
{
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            AttendancePunchLogStatus::Success => 'Success',
            AttendancePunchLogStatus::Failed => 'Failed',
        };
    }
}
