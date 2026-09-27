<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantDepartmentPermission(User $user, Tenant $tenant, PermissionAction $action): void
{
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Departments->value,
        'action' => $action->value,
    ]);
}

it('blocks a non-admin without the View permission from the departments list', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->get(route('departments.index'))->assertForbidden();
});

it('blocks a non-admin without the Create permission from creating a department', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->post(route('departments.store'), ['name' => 'Sales'])->assertForbidden();
    expect(Department::count())->toBe(0);
});

it('allows a non-admin with the Create permission to create a department', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantDepartmentPermission($user, $tenant, PermissionAction::Create);

    $this->actingAs($user)->post(route('departments.store'), ['name' => 'Sales'])
        ->assertRedirect(route('departments.index'));
    expect(Department::count())->toBe(1);
});

it('blocks a non-admin without the Delete permission from deleting a department', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    $department = Department::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->delete(route('departments.destroy', $department))->assertForbidden();
    expect($department->fresh())->not->toBeNull();
});

it('allows a tenant admin to manage departments regardless of granted permissions', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('departments.index'))->assertOk();
    $this->actingAs($admin)->post(route('departments.store'), ['name' => 'Sales'])
        ->assertRedirect(route('departments.index'));
});
