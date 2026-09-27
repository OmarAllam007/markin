<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Department;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantPermission(User $user, Tenant $tenant, PermissionModule $module, PermissionAction $action): void
{
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => $module->value,
        'action' => $action->value,
    ]);
}

// --- canPerform ---

it('always allows a tenant admin regardless of granted permissions', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    expect($admin->canPerform($tenant, PermissionModule::Employees, PermissionAction::Delete))->toBeTrue();
});

it('denies a non-admin with no matching permission grant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    expect($user->canPerform($tenant, PermissionModule::Employees, PermissionAction::Create))->toBeFalse();
});

it('allows a non-admin with the exact permission grant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantPermission($user, $tenant, PermissionModule::Employees, PermissionAction::Create);

    expect($user->canPerform($tenant, PermissionModule::Employees, PermissionAction::Create))->toBeTrue();
});

it('denies a non-admin when the grant is for a different tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantPermission($user, $otherTenant, PermissionModule::Employees, PermissionAction::Create);

    expect($user->canPerform($tenant, PermissionModule::Employees, PermissionAction::Create))->toBeFalse();
});

it('denies a non-admin when the grant is for a different action', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantPermission($user, $tenant, PermissionModule::Employees, PermissionAction::View);

    expect($user->canPerform($tenant, PermissionModule::Employees, PermissionAction::Delete))->toBeFalse();
});

// --- accessibleDepartmentIds / accessibleLocationIds ---

it('returns null (unrestricted) department and location ids for an admin', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    expect($admin->accessibleDepartmentIds($tenant))->toBeNull()
        ->and($admin->accessibleLocationIds($tenant))->toBeNull();
});

it('returns an empty array for a non-admin with no assigned scope', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    expect($user->accessibleDepartmentIds($tenant))->toBe([])
        ->and($user->accessibleLocationIds($tenant))->toBe([]);
});

it('returns only the assigned department and location ids for a scoped non-admin', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $department = Department::factory()->create(['tenant_id' => $tenant->id]);
    Department::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $user->departmentAccess()->attach($department->id, ['tenant_id' => $tenant->id]);
    $user->locationAccess()->attach($location->id, ['tenant_id' => $tenant->id]);

    expect($user->accessibleDepartmentIds($tenant))->toBe([$department->id])
        ->and($user->accessibleLocationIds($tenant))->toBe([$location->id]);
});
