<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Weekend = 'weekend';
    case Holiday = 'holiday';
    case Leave = 'leave';
    case BusinessTrip = 'business_trip';
    case Remote = 'remote';
    case MissingCheckout = 'missing_checkout';

    public function label(): string
    {
        return match ($this) {
            AttendanceStatus::Present => 'Present',
            AttendanceStatus::Absent => 'Absent',
            AttendanceStatus::Late => 'Late',
            AttendanceStatus::HalfDay => 'Half Day',
            AttendanceStatus::Weekend => 'Weekend',
            AttendanceStatus::Holiday => 'Holiday',
            AttendanceStatus::Leave => 'Leave',
            AttendanceStatus::BusinessTrip => 'Business Trip',
            AttendanceStatus::Remote => 'Remote',
            AttendanceStatus::MissingCheckout => 'Missing Checkout',
        };
    }
}
