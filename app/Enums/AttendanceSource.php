<?php

namespace App\Enums;

enum AttendanceSource: string
{
    case Manual = 'manual';
    case Mobile = 'mobile';
    case Biometric = 'biometric';
    case Imported = 'imported';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            AttendanceSource::Manual => 'Manual',
            AttendanceSource::Mobile => 'Mobile',
            AttendanceSource::Biometric => 'Biometric',
            AttendanceSource::Imported => 'Imported',
            AttendanceSource::Api => 'API',
        };
    }
}
