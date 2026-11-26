<?php

use App\Enums\AppModule;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\TenantModule;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 401 without a bearer token', function () {
    $this->getJson(route('api.employee.me'))
        ->assertUnauthorized();
});

it('returns employee profile with no attendance today', function () {
    $shift = WorkShift::factory()->create(['name' => 'Morning', 'checkin_time' => '08:00:00', 'checkout_time' => '16:00:00']);
    $employee = Employee::factory()->create(['work_shift_id' => $shift->id]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.employee.id', $employee->id)
        ->assertJsonPath('data.employee.employee_number', $employee->employee_number)
        ->assertJsonPath('data.employee.english_name', $employee->english_name)
        ->assertJsonPath('data.shift.name', 'Morning')
        ->assertJsonPath('data.shift.scheduled_check_in', '08:00:00')
        ->assertJsonPath('data.today.punch_status', 'not_checked_in')
        ->assertJsonPath('data.today.check_in_time', null)
        ->assertJsonPath('data.today.check_out_time', null);
});

it('returns checked_in status when employee has checked in but not out', function () {
    $employee = Employee::factory()->create();

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'attendance_date' => today()->toDateString(),
        'check_in_time' => '09:00:00',
        'check_out_time' => null,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.today.punch_status', 'checked_in')
        ->assertJsonPath('data.today.check_in_time', '09:00:00')
        ->assertJsonPath('data.today.check_out_time', null);
});

it('returns checked_out status when employee has both check in and check out', function () {
    $employee = Employee::factory()->create();

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'attendance_date' => today()->toDateString(),
        'check_in_time' => '09:00:00',
        'check_out_time' => '17:00:00',
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.today.punch_status', 'checked_out')
        ->assertJsonPath('data.today.check_in_time', '09:00:00')
        ->assertJsonPath('data.today.check_out_time', '17:00:00')
        ->assertJsonPath('data.today.worked_minutes', 480);
});

it('returns day_off status when today is a weekend for the employee shift', function () {
    $dayName = strtolower(now()->englishDayOfWeek);
    $shift = WorkShift::factory()->create(['weekends' => [$dayName]]);
    $employee = Employee::factory()->create(['work_shift_id' => $shift->id]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.today.punch_status', 'day_off');
});

it('returns no_shift status when employee has no assigned shift', function () {
    $employee = Employee::factory()->create(['work_shift_id' => null]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.today.punch_status', 'no_shift')
        ->assertJsonPath('data.shift', null);
});

it('includes enabled_modules in the response', function () {
    $employee = Employee::factory()->create();

    TenantModule::create([
        'tenant_id' => $employee->tenant_id,
        'module' => AppModule::Ticketing->value,
        'is_enabled' => true,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.enabled_modules', ['ticketing']);
});

it('returns empty enabled_modules when no modules are enabled', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.enabled_modules', []);
});

it('includes department and location when assigned', function () {
    $department = Department::factory()->create();
    $location = Location::factory()->create();
    $employee = Employee::factory()->create([
        'department_id' => $department->id,
        'location_id' => $location->id,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.employee.department.id', $department->id)
        ->assertJsonPath('data.employee.location.id', $location->id);
});

it('reports checked_in when the employee checks in again on a multi-session day', function () {
    $shift = WorkShift::factory()->create(['allow_multiple_sessions' => true]);
    $employee = Employee::factory()->create(['work_shift_id' => $shift->id]);
    $attendance = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'shift_id' => $shift->id,
        'attendance_date' => today()->toDateString(),
        'check_in_time' => '09:00:00',
        'check_out_time' => '12:00:00',
    ]);
    AttendancePunch::factory()->forAttendance($attendance)->create();
    AttendancePunch::factory()->forAttendance($attendance)->checkOut()->create();
    AttendancePunch::factory()->forAttendance($attendance)->create();

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.today.punch_status', 'checked_in')
        ->assertJsonPath('data.today.day_complete', false);
});

it('marks the day complete after check-out on a single-session shift', function () {
    $shift = WorkShift::factory()->create(['allow_multiple_sessions' => false]);
    $employee = Employee::factory()->create(['work_shift_id' => $shift->id]);
    $attendance = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'shift_id' => $shift->id,
        'attendance_date' => today()->toDateString(),
        'check_in_time' => '09:00:00',
        'check_out_time' => '17:00:00',
    ]);
    AttendancePunch::factory()->forAttendance($attendance)->create();
    AttendancePunch::factory()->forAttendance($attendance)->checkOut()->create();

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.today.punch_status', 'checked_out')
        ->assertJsonPath('data.today.day_complete', true);
});

it('keeps the day open after check-out on a multi-session shift', function () {
    $shift = WorkShift::factory()->create(['allow_multiple_sessions' => true]);
    $employee = Employee::factory()->create(['work_shift_id' => $shift->id]);
    $attendance = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'shift_id' => $shift->id,
        'attendance_date' => today()->toDateString(),
        'check_in_time' => '09:00:00',
        'check_out_time' => '12:00:00',
    ]);
    AttendancePunch::factory()->forAttendance($attendance)->create();
    AttendancePunch::factory()->forAttendance($attendance)->checkOut()->create();

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.today.punch_status', 'checked_out')
        ->assertJsonPath('data.today.day_complete', false);
});

it('resolves today in the tenant timezone, not the server one', function () {
    // 22:30 UTC is already 01:30 the next day in Riyadh.
    Carbon::setTestNow('2026-11-26 22:30:00');

    $employee = Employee::factory()->create();
    CompanySetting::create(['tenant_id' => $employee->tenant_id, 'timezone' => 'Asia/Riyadh']);
    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'attendance_date' => '2026-11-27',
        'check_in_time' => '01:00:00',
        'check_out_time' => null,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.today.date', '2026-11-27')
        ->assertJsonPath('data.today.punch_status', 'checked_in');

    Carbon::setTestNow();
});

it('keeps an overnight shift on the day it started until checkout time', function () {
    Carbon::setTestNow('2026-05-18 03:00:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '22:00:00',
        'checkout_time' => '06:00:00',
    ]);
    $employee = Employee::factory()->create(['work_shift_id' => $shift->id]);
    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'attendance_date' => '2026-05-17',
        'check_in_time' => '22:00:00',
        'check_out_time' => null,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.today.date', '2026-05-17')
        ->assertJsonPath('data.today.punch_status', 'checked_in');

    Carbon::setTestNow();
});
