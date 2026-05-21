<?php

use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── helpers ──────────────────────────────────────────────────────────────────

function locationWithBounds(array $attributes = []): Location
{
    return Location::factory()->create(array_merge([
        'coordinates' => [
            'north' => 25.0,
            'south' => 24.0,
            'east' => 47.0,
            'west' => 46.0,
        ],
    ], $attributes));
}

// ── auth guard ───────────────────────────────────────────────────────────────

it('returns 401 without a bearer token', function () {
    $this->getJson(route('api.employee.location.check', ['latitude' => 24.5, 'longitude' => 46.5]))
        ->assertUnauthorized();
});

// ── validation ───────────────────────────────────────────────────────────────

it('returns 422 when latitude and longitude are missing', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.location.check'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});

it('returns 422 when coordinates are out of range', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.location.check', ['latitude' => 999, 'longitude' => 999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});

// ── remote check-in ──────────────────────────────────────────────────────────

it('returns within location for employees with remote check-in enabled', function () {
    $employee = Employee::factory()->create(['allow_remote_checkin' => true]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.location.check', ['latitude' => 0.0, 'longitude' => 0.0]))
        ->assertOk()
        ->assertJsonPath('data.is_within_location', true)
        ->assertJsonPath('data.location_name', 'N/A');
});

// ── allow any location ───────────────────────────────────────────────────────

it('returns within location and name when inside any company location', function () {
    $tenant = Tenant::factory()->create();
    $location = locationWithBounds(['tenant_id' => $tenant->id, 'name' => 'Main Office']);
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_any_location_checkin' => true,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.location.check', ['latitude' => 24.5, 'longitude' => 46.5]))
        ->assertOk()
        ->assertJsonPath('data.is_within_location', true)
        ->assertJsonPath('data.location_name', 'Main Office');
});

it('returns not within location when outside all company locations', function () {
    $tenant = Tenant::factory()->create();
    locationWithBounds(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'allow_any_location_checkin' => true,
    ]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.location.check', ['latitude' => 10.0, 'longitude' => 10.0]))
        ->assertOk()
        ->assertJsonPath('data.is_within_location', false)
        ->assertJsonPath('data.location_name', null);
});

// ── assigned location ─────────────────────────────────────────────────────────

it('returns not within location when employee has no assigned location', function () {
    $employee = Employee::factory()->create(['location_id' => null]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.location.check', ['latitude' => 24.5, 'longitude' => 46.5]))
        ->assertOk()
        ->assertJsonPath('data.is_within_location', false)
        ->assertJsonPath('data.location_name', null);
});

it('returns within location and name when inside assigned location', function () {
    $location = locationWithBounds(['name' => 'Riyadh HQ']);
    $employee = Employee::factory()->create(['location_id' => $location->id]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.location.check', ['latitude' => 24.5, 'longitude' => 46.5]))
        ->assertOk()
        ->assertJsonPath('data.is_within_location', true)
        ->assertJsonPath('data.location_name', 'Riyadh HQ');
});

it('returns not within location and still returns location name when outside assigned location', function () {
    $location = locationWithBounds(['name' => 'Riyadh HQ']);
    $employee = Employee::factory()->create(['location_id' => $location->id]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.location.check', ['latitude' => 10.0, 'longitude' => 10.0]))
        ->assertOk()
        ->assertJsonPath('data.is_within_location', false)
        ->assertJsonPath('data.location_name', 'Riyadh HQ');
});
