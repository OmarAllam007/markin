<?php

namespace App\Enums;

/**
 * Where a punch happened relative to the employee's assigned location(s), frozen
 * at punch time. Unknown covers punches with no coordinates (biometric devices,
 * remote punches without GPS) or no location to compare against.
 */
enum PunchLocationStatus: string
{
    case Inside = 'inside';
    case Outside = 'outside';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            PunchLocationStatus::Inside => 'Inside location',
            PunchLocationStatus::Outside => 'Outside location',
            PunchLocationStatus::Unknown => 'Location unknown',
        };
    }
}
