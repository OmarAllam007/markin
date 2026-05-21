<?php

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── helpers ──────────────────────────────────────────────────────────────────

function authenticatedEmployee(array $attributes = []): Employee
{
    return Employee::factory()->create(array_merge([
        'device_fingerprint' => 'fp-abc',
        'allow_remote_checkin' => true,
    ], $attributes));
}

function punchPayload(string $type = 'check_in', array $overrides = []): array
{
    return array_merge(['type' => $type, 'latitude' => 24.7136, 'longitude' => 46.6753], $overrides);
}

// ── auth guard ───────────────────────────────────────────────────────────────

it('returns 401 without a bearer token', function () {
    $this->postJson(route('api.employee.attendance.punch'), punchPayload())
        ->assertUnauthorized();
});

// ── validation ───────────────────────────────────────────────────────────────

it('returns 422 when type is missing', function () {
    $employee = authenticatedEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('returns 422 when type is invalid', function () {
    $employee = authenticatedEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'lunch'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

// ── check in ─────────────────────────────────────────────────────────────────

it('creates attendance and punch record on first check in', function () {
    $employee = authenticatedEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk()
        ->assertJsonPath('data.type', 'check_in');

    expect(Attendance::where('employee_id', $employee->id)->count())->toBe(1)
        ->and(AttendancePunch::count())->toBe(1);
});

it('reuses today\'s attendance record on subsequent punches', function () {
    $employee = authenticatedEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'));

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_out'));

    expect(Attendance::where('employee_id', $employee->id)->count())->toBe(1)
        ->and(AttendancePunch::count())->toBe(2);
});

it('sets check_in_time and computes worked_minutes after check out', function () {
    $employee = authenticatedEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk();

    // Move the check-in punch back 8 hours so worked_minutes is meaningful
    AttendancePunch::first()->update(['punched_at' => now()->subHours(8)]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_out'))
        ->assertOk();

    $attendance = Attendance::where('employee_id', $employee->id)->first();

    expect($attendance->check_in_time)->not->toBeNull()
        ->and($attendance->check_out_time)->not->toBeNull()
        ->and($attendance->worked_minutes)->toBeGreaterThan(0);
});

// ── sequence validation ───────────────────────────────────────────────────────

it('rejects a second check in while already checked in', function () {
    $employee = authenticatedEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'));

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertUnprocessable()
        ->assertJsonPath('errors.type.0', 'You are already checked in.');
});

it('rejects check out without a prior check in', function () {
    $employee = authenticatedEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_out'))
        ->assertUnprocessable()
        ->assertJsonPath('errors.type.0', 'You must check in before checking out.');
});

// ── single-session policy ─────────────────────────────────────────────────────

it('rejects a second session on a single-session shift', function () {
    $shift = WorkShift::factory()->create(['allow_multiple_sessions' => false]);
    $employee = authenticatedEmployee(['work_shift_id' => $shift->id]);

    // Complete one full session
    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'));
    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_out'));

    // Second check-in should be blocked
    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertUnprocessable()
        ->assertJsonPath('errors.type.0', 'Your shift does not allow multiple sessions per day.');
});

it('allows a second session on a multi-session shift', function () {
    $shift = WorkShift::factory()->create(['allow_multiple_sessions' => true]);
    $employee = authenticatedEmployee(['work_shift_id' => $shift->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'));
    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_out'));

    // Second check-in should succeed
    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk();

    expect(AttendancePunch::count())->toBe(3);
});

it('accumulates worked_minutes across multiple sessions', function () {
    $shift = WorkShift::factory()->create(['allow_multiple_sessions' => true]);
    $employee = authenticatedEmployee(['work_shift_id' => $shift->id]);

    // Session 1: 4 hours
    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'));
    AttendancePunch::latest('id')->first()->update(['punched_at' => now()->subHours(8)]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_out'));
    AttendancePunch::latest('id')->first()->update(['punched_at' => now()->subHours(4)]);

    // Session 2: 2 hours
    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'));
    AttendancePunch::latest('id')->first()->update(['punched_at' => now()->subHours(2)]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_out'));

    $attendance = Attendance::where('employee_id', $employee->id)->first();

    expect($attendance->worked_minutes)->toBeGreaterThan(0);
});

// ── overnight shift support ───────────────────────────────────────────────────

it('assigns attendance_date to the shift start day for overnight check-in', function () {
    Carbon::setTestNow('2026-05-17 22:00:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '22:00:00',
        'checkout_time' => '06:00:00',
    ]);
    $employee = authenticatedEmployee(['work_shift_id' => $shift->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk();

    Carbon::setTestNow();

    $attendance = Attendance::where('employee_id', $employee->id)->first();
    expect($attendance)->not->toBeNull()
        ->and($attendance->attendance_date->toDateString())->toBe('2026-05-17');
});

it('attaches an after-midnight checkout to the previous day attendance', function () {
    // Check-in at 22:00 on day 1
    Carbon::setTestNow('2026-05-17 22:00:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '22:00:00',
        'checkout_time' => '06:00:00',
    ]);
    $employee = authenticatedEmployee(['work_shift_id' => $shift->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk();

    // Check-out at 02:00 on day 2 — still within the overnight window
    Carbon::setTestNow('2026-05-18 02:00:00');

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_out'))
        ->assertOk();

    Carbon::setTestNow();

    expect(Attendance::where('employee_id', $employee->id)->count())->toBe(1);

    $attendance = Attendance::where('employee_id', $employee->id)->first();
    expect($attendance->attendance_date->toDateString())->toBe('2026-05-17')
        ->and($attendance->worked_minutes)->toBe(240); // 4 hours = 240 minutes
});

it('correctly calculates worked_minutes across midnight for overnight shift', function () {
    Carbon::setTestNow('2026-05-17 23:00:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '22:00:00',
        'checkout_time' => '06:00:00',
    ]);
    $employee = authenticatedEmployee(['work_shift_id' => $shift->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk();

    Carbon::setTestNow('2026-05-18 05:00:00');

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_out'))
        ->assertOk();

    Carbon::setTestNow();

    $attendance = Attendance::where('employee_id', $employee->id)->first();
    expect($attendance->worked_minutes)->toBe(360); // 6 hours = 360 minutes
});

it('calculates late minutes correctly for an overnight shift', function () {
    // Employee checks in 15 minutes late for the 22:00 overnight shift
    Carbon::setTestNow('2026-05-17 22:15:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '22:00:00',
        'checkout_time' => '06:00:00',
    ]);
    $employee = authenticatedEmployee(['work_shift_id' => $shift->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk();

    Carbon::setTestNow();

    $attendance = Attendance::where('employee_id', $employee->id)->first();
    expect($attendance->total_late_minutes)->toBe(15);
});

it('does not mark as late when employee checks in on time for overnight shift', function () {
    Carbon::setTestNow('2026-05-17 22:00:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '22:00:00',
        'checkout_time' => '06:00:00',
    ]);
    $employee = authenticatedEmployee(['work_shift_id' => $shift->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk();

    Carbon::setTestNow();

    $attendance = Attendance::where('employee_id', $employee->id)->first();
    expect($attendance->total_late_minutes)->toBe(0);
});

it('creates a new attendance for today when punch arrives after overnight window closes', function () {
    // Shift: 22:00 → 06:00. A punch at 07:00 is outside the window → new day.
    Carbon::setTestNow('2026-05-17 22:00:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '22:00:00',
        'checkout_time' => '06:00:00',
    ]);
    $employee = authenticatedEmployee(['work_shift_id' => $shift->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk();

    // Advance to 07:00 next day — outside the overnight window
    Carbon::setTestNow('2026-05-18 07:00:00');

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk();

    Carbon::setTestNow();

    // Two separate attendance records: one per calendar day
    expect(Attendance::where('employee_id', $employee->id)->count())->toBe(2);
});

it('stores check_in_time and check_out_time in tenant timezone for overnight punches', function () {
    Carbon::setTestNow('2026-05-17 22:00:00');

    $shift = WorkShift::factory()->create([
        'type' => 'fixed',
        'checkin_time' => '22:00:00',
        'checkout_time' => '06:00:00',
    ]);
    $employee = authenticatedEmployee(['work_shift_id' => $shift->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_in'))
        ->assertOk();

    Carbon::setTestNow('2026-05-18 04:00:00');

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), punchPayload('check_out'))
        ->assertOk();

    Carbon::setTestNow();

    $attendance = Attendance::where('employee_id', $employee->id)->first();
    // Timezone defaults to UTC in tests, so stored times match UTC punch times
    expect($attendance->check_in_time)->toBe('22:00:00')
        ->and($attendance->check_out_time)->toBe('04:00:00');
});
