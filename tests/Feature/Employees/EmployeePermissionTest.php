<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantEmployeePermission(User $user, Tenant $tenant, PermissionAction $action): void
{
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Employees->value,
        'action' => $action->value,
    ]);
}

function employeePayload(): array
{
    return [
        'arabic_name' => 'أحمد علي',
        'english_name' => 'Ahmed Ali',
        'mobile_country_code' => '+966',
        'mobile_number' => '500000001',
    ];
}

it('blocks a non-admin without the View permission from the employees list', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->get(route('employees.index'))->assertForbidden();
});

it('allows a non-admin with the View permission to see the employees list', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantEmployeePermission($user, $tenant, PermissionAction::View);

    $this->actingAs($user)->get(route('employees.index'))->assertOk();
});

it('blocks a non-admin without the Create permission from creating an employee', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->post(route('employees.store'), employeePayload())->assertForbidden();
    expect(Employee::count())->toBe(0);
});

it('allows a non-admin with the Create permission to create an employee', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantEmployeePermission($user, $tenant, PermissionAction::Create);

    $this->actingAs($user)->post(route('employees.store'), employeePayload())
        ->assertRedirect(route('employees.index'));
    expect(Employee::count())->toBe(1);
});

it('blocks a non-admin without the Edit permission from updating an employee', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->put(route('employees.update', $employee), employeePayload())
        ->assertForbidden();
});

it('blocks a non-admin without the Delete permission from deleting an employee', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->delete(route('employees.destroy', $employee))->assertForbidden();
    expect($employee->fresh())->not->toBeNull();
});

it('allows a tenant admin to manage employees regardless of granted permissions', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('employees.index'))->assertOk();
    $this->actingAs($admin)->post(route('employees.store'), employeePayload())
        ->assertRedirect(route('employees.index'));
});
