<?php

use App\DataTransferObjects\AttendanceCalculationInput;
use App\Enums\AttendanceStatus;
use App\Enums\ShiftType;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceCalculatorService;
use Carbon\CarbonImmutable;

function makeFixedShift(int $lateGrace = 0, int $earlyGrace = 0): WorkShift
{
    return new WorkShift([
        'type' => ShiftType::Fixed,
        'checkin_time' => '09:00',
        'checkout_time' => '17:00',
        'overtime_enabled' => false,
        'late_checkin_grace_minutes' => $lateGrace,
        'early_checkout_grace_minutes' => $earlyGrace,
    ]);
}

function makeInput(
    string $checkIn,
    string $checkOut,
    WorkShift $shift,
    string $scheduledIn = '09:00',
    string $scheduledOut = '17:00',
): AttendanceCalculationInput {
    $date = CarbonImmutable::parse('2026-05-19');

    return AttendanceCalculationInput::fromPrimitives(
        attendanceDate: $date,
        checkInTime: $checkIn,
        checkOutTime: $checkOut,
        shift: $shift,
        companySettings: null,
        isWeekend: false,
        isHoliday: false,
        status: AttendanceStatus::Present,
        breakMinutesDeduction: 0,
        scheduledCheckInTime: $scheduledIn,
        scheduledCheckOutTime: $scheduledOut,
    );
}

$calculator = fn () => new AttendanceCalculatorService;

it('marks employee as late when no grace is set', function () use ($calculator) {
    $shift = makeFixedShift(lateGrace: 0);
    $result = ($calculator())->calculate(makeInput('09:05', '17:00', $shift));

    expect($result->totalLateMinutes)->toBe(5)
        ->and($result->totalEarlyLeaveMinutes)->toBe(0);
});

it('ignores lateness within grace period', function () use ($calculator) {
    $shift = makeFixedShift(lateGrace: 10);
    $result = ($calculator())->calculate(makeInput('09:08', '17:00', $shift));

    expect($result->totalLateMinutes)->toBe(0);
});

it('counts lateness that exceeds grace period', function () use ($calculator) {
    $shift = makeFixedShift(lateGrace: 5);
    $result = ($calculator())->calculate(makeInput('09:10', '17:00', $shift));

    expect($result->totalLateMinutes)->toBe(10);
});

it('marks employee as early leave when no grace is set', function () use ($calculator) {
    $shift = makeFixedShift(earlyGrace: 0);
    $result = ($calculator())->calculate(makeInput('09:00', '16:55', $shift));

    expect($result->totalEarlyLeaveMinutes)->toBe(5)
        ->and($result->totalLateMinutes)->toBe(0);
});

it('ignores early checkout within grace period', function () use ($calculator) {
    $shift = makeFixedShift(earlyGrace: 10);
    $result = ($calculator())->calculate(makeInput('09:00', '16:53', $shift));

    expect($result->totalEarlyLeaveMinutes)->toBe(0);
});

it('counts early leave that exceeds grace period', function () use ($calculator) {
    $shift = makeFixedShift(earlyGrace: 5);
    $result = ($calculator())->calculate(makeInput('09:00', '16:50', $shift));

    expect($result->totalEarlyLeaveMinutes)->toBe(10);
});

it('applies both grace windows independently', function () use ($calculator) {
    $shift = makeFixedShift(lateGrace: 10, earlyGrace: 10);
    $result = ($calculator())->calculate(makeInput('09:08', '16:52', $shift));

    expect($result->totalLateMinutes)->toBe(0)
        ->and($result->totalEarlyLeaveMinutes)->toBe(0);
});

it('counts late but not early leave when only late exceeds grace', function () use ($calculator) {
    $shift = makeFixedShift(lateGrace: 5, earlyGrace: 10);
    $result = ($calculator())->calculate(makeInput('09:15', '16:52', $shift));

    expect($result->totalLateMinutes)->toBe(15)
        ->and($result->totalEarlyLeaveMinutes)->toBe(0);
});
