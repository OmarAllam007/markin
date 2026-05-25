<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── attendance index report ───────────────────────────────────────────────────

it('requires authentication to access attendance report', function () {
    $this->getJson(route('api.employee.attendance.index'))
        ->assertUnauthorized();
});

it('returns paginated attendance records for the authenticated employee', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);

    Attendance::factory()->count(3)->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'attendance_date' => now()->toDateString(),
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.attendance.index'))
        ->assertOk()
        ->assertJsonPath('data.total', 3);
});

it('does not return another employee attendance records', function () {
    $employee = Employee::factory()->create();
    $other = Employee::factory()->create();

    Attendance::factory()->count(2)->create([
        'employee_id' => $other->id,
        'tenant_id' => $other->tenant_id,
        'attendance_date' => now()->toDateString(),
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.attendance.index'))
        ->assertOk()
        ->assertJsonPath('data.total', 0);
});

it('filters attendance records by date range', function () {
    $employee = Employee::factory()->create();

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'attendance_date' => '2026-04-15',
    ]);
    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'attendance_date' => '2026-05-15',
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.attendance.index', ['date_from' => '2026-05-01', 'date_to' => '2026-05-31']))
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

// ── missing records report ────────────────────────────────────────────────────

it('requires authentication to access missing report', function () {
    $this->getJson(route('api.employee.attendance.missing'))
        ->assertUnauthorized();
});

it('identifies absent working days with no attendance record', function () {
    Carbon::setTestNow('2026-05-24');

    $shift = WorkShift::factory()->create([
        'weekends' => ['friday', 'saturday'],
    ]);
    $employee = Employee::factory()->create([
        'work_shift_id' => $shift->id,
        'tenant_id' => $shift->tenant_id,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.attendance.missing', [
            'date_from' => '2026-05-19', // Monday
            'date_to' => '2026-05-21',   // Wednesday
        ]))
        ->assertOk();

    $records = $response->json('data.records');

    expect($response->json('data.total_missing'))->toBeGreaterThanOrEqual(3)
        ->and(collect($records)->pluck('type')->unique()->toArray())->toContain('absent');

    Carbon::setTestNow();
});

it('skips weekends when identifying missing records', function () {
    Carbon::setTestNow('2026-05-24');

    $shift = WorkShift::factory()->create([
        'weekends' => ['friday', 'saturday'],
    ]);
    $employee = Employee::factory()->create([
        'work_shift_id' => $shift->id,
        'tenant_id' => $shift->tenant_id,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.attendance.missing', [
            'date_from' => '2026-05-22', // Friday
            'date_to' => '2026-05-23',   // Saturday
        ]))
        ->assertOk();

    expect($response->json('data.total_missing'))->toBe(0);

    Carbon::setTestNow();
});

it('identifies missing checkout records', function () {
    Carbon::setTestNow('2026-05-24');

    $shift = WorkShift::factory()->create(['weekends' => ['friday', 'saturday']]);
    $employee = Employee::factory()->create([
        'work_shift_id' => $shift->id,
        'tenant_id' => $shift->tenant_id,
    ]);

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'tenant_id' => $employee->tenant_id,
        'attendance_date' => '2026-05-19',
        'check_in_time' => '09:00:00',
        'check_out_time' => null,
    ]);

    $response = $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.attendance.missing', [
            'date_from' => '2026-05-19',
            'date_to' => '2026-05-19',
        ]))
        ->assertOk();

    $records = $response->json('data.records');

    expect(collect($records)->firstWhere('type', 'missing_checkout'))->not->toBeNull();

    Carbon::setTestNow();
});
