<?php

use App\Enums\AttendancePunchLogStatus;
use App\Enums\AttendanceSource;
use App\Models\AttendancePunch;
use App\Models\AttendancePunchLog;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── successful punches ────────────────────────────────────────────────────────

it('writes a success log entry on check in', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), [
            'type' => 'check_in',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'device_name' => 'iPhone 15',
        ])
        ->assertOk();

    $log = AttendancePunchLog::where('employee_id', $employee->id)->sole();

    expect($log->status)->toBe(AttendancePunchLogStatus::Success)
        ->and($log->source)->toBe(AttendanceSource::Mobile)
        ->and($log->punch_type->value)->toBe('check_in')
        ->and($log->failure_reason)->toBeNull()
        ->and($log->attendance_punch_id)->toBe(AttendancePunch::first()->id)
        ->and($log->device_name)->toBe('iPhone 15')
        ->and((float) $log->latitude)->toBe(24.7136)
        ->and((float) $log->longitude)->toBe(46.6753);
});

it('captures ip address and user agent on success', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(
            route('api.employee.attendance.punch'),
            ['type' => 'check_in', 'latitude' => 24.7136, 'longitude' => 46.6753],
            ['User-Agent' => 'TestApp/1.0'],
        )
        ->assertOk();

    $log = AttendancePunchLog::where('employee_id', $employee->id)->sole();

    expect($log->user_agent)->toBe('TestApp/1.0')
        ->and($log->ip_address)->not->toBeNull();
});

// ── failed punches ────────────────────────────────────────────────────────────

it('writes a failed log entry when location validation fails', function () {
    $employee = Employee::factory()->create([
        'allow_remote_checkin' => false,
        'allow_any_location_checkin' => false,
        'location_id' => null,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), [
            'type' => 'check_in',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
        ])
        ->assertUnprocessable();

    $log = AttendancePunchLog::where('employee_id', $employee->id)->sole();

    expect($log->status)->toBe(AttendancePunchLogStatus::Failed)
        ->and($log->failure_reason)->not->toBeEmpty()
        ->and($log->attendance_punch_id)->toBeNull();
});

it('writes a failed log entry when punch sequence is invalid', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);

    // First check-in succeeds
    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_in'])
        ->assertOk();

    // Second check-in while already checked in
    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_in'])
        ->assertUnprocessable();

    $logs = AttendancePunchLog::where('employee_id', $employee->id)->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->first()->status)->toBe(AttendancePunchLogStatus::Success)
        ->and($logs->last()->status)->toBe(AttendancePunchLogStatus::Failed)
        ->and($logs->last()->failure_reason)->toBe('You are already checked in.');
});

// ── tenant scoping ────────────────────────────────────────────────────────────

it('stores the correct tenant_id on the log', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_in'])
        ->assertOk();

    $log = AttendancePunchLog::where('employee_id', $employee->id)->sole();

    expect($log->tenant_id)->toBe($employee->tenant_id);
});
