<?php

use App\Enums\PunchLocationStatus;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/** A remote worker whose assigned office is a 1°×1° box in Riyadh. */
function remoteEmployee(array $overrides = []): Employee
{
    $location = Location::factory()->create([
        'coordinates' => ['north' => 25.0, 'south' => 24.0, 'east' => 47.0, 'west' => 46.0],
    ]);

    return Employee::factory()->create([
        'tenant_id' => $location->tenant_id,
        'location_id' => $location->id,
        'allow_remote_checkin' => true,
        'allow_any_location_checkin' => false,
        'device_fingerprint' => 'fp-abc',
        ...$overrides,
    ]);
}

function punchAs(Employee $employee, array $payload): AttendancePunch
{
    test()->actingAs($employee, 'sanctum')
        ->postJson(route('api.employee.attendance.punch'), ['type' => 'check_in', ...$payload])
        ->assertSuccessful();

    return AttendancePunch::sole();
}

it('marks a remote punch from abroad as outside, with the distance', function () {
    $punch = punchAs(remoteEmployee(), ['latitude' => 40.71, 'longitude' => -74.0]);

    expect($punch->location_status)->toBe(PunchLocationStatus::Outside)
        ->and($punch->distance_from_location_m)->toBeGreaterThan(10_000_000);
});

it('measures distance to the nearest edge of the location, not its centre', function () {
    // 0.1° north of the box's north edge ≈ 11.1 km.
    $punch = punchAs(remoteEmployee(), ['latitude' => 25.1, 'longitude' => 46.5]);

    expect($punch->location_status)->toBe(PunchLocationStatus::Outside)
        ->and($punch->distance_from_location_m)->toBeBetween(11_000, 11_200);
});

it('marks a remote punch inside the assigned location as inside', function () {
    $punch = punchAs(remoteEmployee(), ['latitude' => 24.5, 'longitude' => 46.5]);

    expect($punch->location_status)->toBe(PunchLocationStatus::Inside)
        ->and($punch->distance_from_location_m)->toBe(0);
});

it('marks a punch without coordinates as unknown, not outside', function () {
    $punch = punchAs(remoteEmployee(), []);

    expect($punch->location_status)->toBe(PunchLocationStatus::Unknown)
        ->and($punch->distance_from_location_m)->toBeNull();
});

it('marks a remote punch as unknown when there is no location to compare against', function () {
    $punch = punchAs(remoteEmployee(['location_id' => null]), ['latitude' => 40.71, 'longitude' => -74.0]);

    expect($punch->location_status)->toBe(PunchLocationStatus::Unknown);
});

it('measures from the nearest company location for any-location employees', function () {
    $employee = remoteEmployee(['allow_any_location_checkin' => true]);
    Location::factory()->create([
        'tenant_id' => $employee->tenant_id,
        'coordinates' => ['north' => 22.0, 'south' => 21.0, 'east' => 40.0, 'west' => 39.0],
    ]);

    // 0.1° east of the second box ≈ 10.4 km; the Riyadh box is ~650 km away.
    $punch = punchAs($employee, ['latitude' => 21.5, 'longitude' => 40.1]);

    expect($punch->location_status)->toBe(PunchLocationStatus::Outside)
        ->and($punch->distance_from_location_m)->toBeBetween(10_000, 11_000);
});

it('flags and filters attendance days with a punch outside the location', function () {
    $tenant = Tenant::factory()->create();
    CompanySetting::create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $remoteDay = Attendance::factory()->forEmployee($employee)->forDate(now())->create();
    AttendancePunch::factory()->forAttendance($remoteDay)->create([
        'location_status' => PunchLocationStatus::Outside,
        'distance_from_location_m' => 11_000_000,
    ]);
    $officeDay = Attendance::factory()->forEmployee($employee)->forDate(now()->subDay())->create();
    AttendancePunch::factory()->forAttendance($officeDay)->create([
        'location_status' => PunchLocationStatus::Inside,
    ]);

    $this->actingAs($admin)
        ->get(route('attendances.index', ['date_from' => now()->subDays(2)->toDateString()]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('attendances.data', 2)
            ->where('attendances.data.0.has_outside_punch', true)
            ->where('attendances.data.1.has_outside_punch', false));

    $this->actingAs($admin)
        ->get(route('attendances.index', ['date_from' => now()->subDays(2)->toDateString(), 'outside_location' => 1]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('attendances.data', 1)
            ->where('attendances.data.0.id', $remoteDay->id));
});
