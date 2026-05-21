<?php

namespace App\DataTransferObjects;

use App\Enums\AttendanceStatus;
use App\Models\CompanySetting;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceCalculatorService;
use Carbon\CarbonImmutable;

/**
 * Immutable input for {@see AttendanceCalculatorService}.
 *
 * All clock arithmetic uses the tenant-local calendar date combined with wall-clock times.
 * Future: inject timezone per tenant when operating across regions.
 */
final readonly class AttendanceCalculationInput
{
    /**
     * @param  CarbonImmutable|null  $checkIn  Full datetime (date + check-in time)
     * @param  CarbonImmutable|null  $checkOut  Full datetime (date + check-out time)
     */
    public function __construct(
        public CarbonImmutable $attendanceDate,
        public ?CarbonImmutable $checkIn,
        public ?CarbonImmutable $checkOut,
        public ?WorkShift $shift,
        public ?CompanySetting $companySettings,
        public bool $isWeekend,
        public bool $isHoliday,
        public AttendanceStatus $status,
        public int $breakMinutesDeduction,
        public ?CarbonImmutable $scheduledCheckIn = null,
        public ?CarbonImmutable $scheduledCheckOut = null,
    ) {}

    public static function fromPrimitives(
        CarbonImmutable $attendanceDate,
        ?string $checkInTime,
        ?string $checkOutTime,
        ?WorkShift $shift,
        ?CompanySetting $companySettings,
        bool $isWeekend,
        bool $isHoliday,
        AttendanceStatus $status,
        int $breakMinutesDeduction,
        ?string $scheduledCheckInTime = null,
        ?string $scheduledCheckOutTime = null,
    ): self {
        $dateStr = $attendanceDate->toDateString();

        $checkIn = $checkInTime !== null && $checkInTime !== ''
            ? CarbonImmutable::parse($dateStr.' '.$checkInTime)
            : null;

        $checkOut = $checkOutTime !== null && $checkOutTime !== ''
            ? CarbonImmutable::parse($dateStr.' '.$checkOutTime)
            : null;

        $scheduledIn = $scheduledCheckInTime !== null && $scheduledCheckInTime !== ''
            ? CarbonImmutable::parse($dateStr.' '.$scheduledCheckInTime)
            : null;

        $scheduledOut = $scheduledCheckOutTime !== null && $scheduledCheckOutTime !== ''
            ? CarbonImmutable::parse($dateStr.' '.$scheduledCheckOutTime)
            : null;

        return new self(
            attendanceDate: $attendanceDate,
            checkIn: $checkIn,
            checkOut: $checkOut,
            shift: $shift,
            companySettings: $companySettings,
            isWeekend: $isWeekend,
            isHoliday: $isHoliday,
            status: $status,
            breakMinutesDeduction: $breakMinutesDeduction,
            scheduledCheckIn: $scheduledIn,
            scheduledCheckOut: $scheduledOut,
        );
    }
}
