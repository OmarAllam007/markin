<?php

use App\Enums\AttendancePunchLogStatus;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** An employee tied to one office, standing right inside it. */
function officeEmployee(): Employee
{
    $location = Location::factory()->create([
        'coordinates' => ['north' => 25.0, 'south' => 24.0, 'east' => 47.0, 'west' => 46.0],
    ]);

    return Employee::factory()->create([
        'tenant_id' => $location->tenant_id,
        'location_id' => $location->id,
        'allow_remote_checkin' => false,
        'allow_any_location_checkin' => false,
        'device_fingerprint' => 'fp-abc',
    ]);
}

function insideOffice(array $overrides = []): array
{
    return array_merge(['type' => 'check_in', 'latitude' => 24.5, 'longitude' => 46.5], $overrides);
}

// ── mock location ────────────────────────────────────────────────────────────

it('refuses a punch whose coordinates come from a mock provider', function () {
    $employee = officeEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), insideOffice(['is_mocked' => true]))
        ->assertStatus(422)
        ->assertJsonPath('status', false);

    $this->assertDatabaseCount('attendance_punches', 0);
});

it('records the refused attempt as evidence', function () {
    $employee = officeEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), insideOffice([
            'is_mocked' => true,
            'is_rooted' => true,
            'gps_accuracy' => 8.0,
        ]));

    $this->assertDatabaseHas('attendance_punch_logs', [
        'employee_id' => $employee->id,
        'status' => AttendancePunchLogStatus::Failed->value,
        'is_mocked' => true,
        'is_rooted' => true,
        'latitude' => 24.5,
    ]);
});

it('still accepts a mocked punch for an employee allowed to work remotely', function () {
    $employee = Employee::factory()->create([
        'allow_remote_checkin' => true,
        'device_fingerprint' => 'fp-abc',
    ]);

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), insideOffice(['is_mocked' => true]))
        ->assertOk();
});

// ── GPS accuracy ─────────────────────────────────────────────────────────────

it('refuses a fix too coarse to prove the employee is on site', function () {
    $employee = officeEmployee();

    $response = $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), insideOffice(['gps_accuracy' => 1500]))
        ->assertStatus(422);

    expect($response->json('errors.location.0'))->toContain('1500');
    $this->assertDatabaseCount('attendance_punches', 0);
});

it('accepts a sharp fix and stores the accuracy with the punch', function () {
    $employee = officeEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), insideOffice(['gps_accuracy' => 12.5]))
        ->assertOk();

    $this->assertDatabaseHas('attendance_punches', [
        'gps_accuracy' => 12.5,
        'is_mocked' => false,
    ]);
});

// ── rooted / emulator are flagged, not blocked, by default ───────────────────

it('lets a rooted device punch but keeps the flag on the record', function () {
    $employee = officeEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), insideOffice([
            'is_rooted' => true,
            'is_emulator' => true,
        ]))
        ->assertOk();

    $this->assertDatabaseHas('attendance_punches', ['is_rooted' => true, 'is_emulator' => true]);
});

it('blocks a rooted device once the deployment turns the gate on', function () {
    config(['attendance.reject_rooted_devices' => true]);
    $employee = officeEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), insideOffice(['is_rooted' => true]))
        ->assertStatus(422);
});

it('keeps punching when the phone reports nothing extra', function () {
    $employee = officeEmployee();

    $this->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), insideOffice())
        ->assertOk();

    $this->assertDatabaseHas('attendance_punches', ['is_mocked' => false, 'gps_accuracy' => null]);
});
