<?php

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\ShiftType;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Models\WorkShift;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 2026-11-08 is a Sunday, 2026-11-09 a Monday. Shifts are 09:00–17:00, off Fri/Sat, +2h grace.

function absenceShift(Tenant $tenant, array $overrides = []): WorkShift
{
    return WorkShift::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => ShiftType::Fixed,
        'checkin_time' => '09:00',
        'checkout_time' => '17:00',
        'weekends' => ['friday', 'saturday'],
        ...$overrides,
    ]);
}

function shiftEmployee(WorkShift $shift, array $overrides = []): Employee
{
    return Employee::factory()->create([
        'tenant_id' => $shift->tenant_id,
        'work_shift_id' => $shift->id,
        ...$overrides,
    ]);
}

function statusOn(Employee $employee, string $date): ?AttendanceStatus
{
    return Attendance::where('employee_id', $employee->id)->whereDate('attendance_date', $date)->first()?->status;
}

it('marks a no-show absent only once the shift end plus grace has passed, without duplicates', function () {
    $employee = shiftEmployee(absenceShift(Tenant::factory()->create()));

    $this->travelTo(CarbonImmutable::parse('2026-11-09 18:59', 'UTC'));
    $this->artisan('attendance:mark-absences')->assertSuccessful();

    expect(statusOn($employee, '2026-11-08'))->toBe(AttendanceStatus::Absent)
        ->and(statusOn($employee, '2026-11-09'))->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-11-09 19:00', 'UTC'));
    $this->artisan('attendance:mark-absences')->assertSuccessful();
    $this->artisan('attendance:mark-absences')->assertSuccessful();

    expect(statusOn($employee, '2026-11-09'))->toBe(AttendanceStatus::Absent)
        ->and(Attendance::where('employee_id', $employee->id)->count())->toBe(2);
});

it('skips weekends, inactive employees, employees who punched, and those not started yet', function () {
    $shift = absenceShift(Tenant::factory()->create());
    $noShow = shiftEmployee($shift);
    $inactive = shiftEmployee($shift, ['status' => EmployeeStatus::Terminated]);
    $notStarted = shiftEmployee($shift, ['working_start_date' => '2026-11-10']);
    $attended = shiftEmployee($shift);
    Attendance::factory()->forEmployee($attended)->forDate(CarbonImmutable::parse('2026-11-08'))->create();

    $this->travelTo(CarbonImmutable::parse('2026-11-08 20:00', 'UTC'));
    $this->artisan('attendance:mark-absences')->assertSuccessful();

    expect(statusOn($noShow, '2026-11-08'))->toBe(AttendanceStatus::Absent)
        ->and(statusOn($noShow, '2026-11-07'))->toBeNull() // Saturday = weekend
        ->and(statusOn($inactive, '2026-11-08'))->toBeNull()
        ->and(statusOn($notStarted, '2026-11-08'))->toBeNull()
        ->and(statusOn($attended, '2026-11-08'))->toBe(AttendanceStatus::Present);
});

it('does not mark anyone absent on a holiday', function () {
    $tenant = Tenant::factory()->create();
    $employee = shiftEmployee(absenceShift($tenant));
    Holiday::factory()->create(['tenant_id' => $tenant->id, 'date' => '2026-11-08']);

    $this->travelTo(CarbonImmutable::parse('2026-11-08 20:00', 'UTC'));
    $this->artisan('attendance:mark-absences')->assertSuccessful();

    expect(statusOn($employee, '2026-11-08'))->toBeNull();
});

it('does not recreate an attendance an admin deleted', function () {
    $employee = shiftEmployee(absenceShift(Tenant::factory()->create()));
    Attendance::factory()->forEmployee($employee)->forDate(CarbonImmutable::parse('2026-11-08'))->create()->delete();

    $this->travelTo(CarbonImmutable::parse('2026-11-08 20:00', 'UTC'));
    $this->artisan('attendance:mark-absences')->assertSuccessful();

    expect(Attendance::withTrashed()->where('employee_id', $employee->id)->count())->toBe(1);
});

it('flags a check-in without a check-out as missing checkout', function () {
    $employee = shiftEmployee(absenceShift(Tenant::factory()->create()));
    Attendance::factory()->forEmployee($employee)->forDate(CarbonImmutable::parse('2026-11-08'))
        ->create(['check_in_time' => '09:05:00', 'check_out_time' => null]);

    $this->travelTo(CarbonImmutable::parse('2026-11-08 20:00', 'UTC'));
    $this->artisan('attendance:mark-absences')->assertSuccessful();

    expect(statusOn($employee, '2026-11-08'))->toBe(AttendanceStatus::MissingCheckout);
});

it('waits for an overnight shift to end the next morning', function () {
    $employee = shiftEmployee(absenceShift(Tenant::factory()->create(), ['checkin_time' => '22:00', 'checkout_time' => '06:00']));

    $this->travelTo(CarbonImmutable::parse('2026-11-09 07:59', 'UTC'));
    $this->artisan('attendance:mark-absences')->assertSuccessful();
    expect(statusOn($employee, '2026-11-08'))->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-11-09 08:00', 'UTC'));
    $this->artisan('attendance:mark-absences')->assertSuccessful();
    expect(statusOn($employee, '2026-11-08'))->toBe(AttendanceStatus::Absent);
});

it('uses the tenant timezone to decide when a shift has ended', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id, 'timezone' => 'Asia/Riyadh']);
    $employee = shiftEmployee(absenceShift($tenant));

    // 16:00 UTC = 19:00 in Riyadh: the Monday shift is closed there, not yet in UTC.
    $this->travelTo(CarbonImmutable::parse('2026-11-09 16:00', 'UTC'));
    $this->artisan('attendance:mark-absences')->assertSuccessful();

    expect(statusOn($employee, '2026-11-09'))->toBe(AttendanceStatus::Absent);
});
