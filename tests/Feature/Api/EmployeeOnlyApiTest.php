<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// auth:sanctum falls back to the session guard, so an admin browsing an
// employee/* URL arrives authenticated as a User rather than an Employee.

it('turns away a signed-in admin user', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)
        ->getJson(route('api.employee.announcements.index'))
        ->assertForbidden()
        ->assertJsonPath('status', false);
});

it('lets an active employee through', function () {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($employee, 'sanctum')
        ->getJson(route('api.employee.announcements.index'))
        ->assertSuccessful();
});
