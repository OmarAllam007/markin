<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function employeeWithDevice(Tenant $tenant): Employee
{
    $employee = Employee::factory()->create([
        'tenant_id' => $tenant->id,
        'device_fingerprint' => hash('sha256', 'old-phone'),
        'device_name' => 'Samsung S24',
    ]);
    $employee->createToken('Samsung S24');

    return $employee;
}

it('unbinds the device, signs out the old phone, and lets a new phone register', function () {
    $tenant = Tenant::factory()->create();
    $employee = employeeWithDevice($tenant);

    $this->actingAs(User::factory()->admin($tenant)->create())
        ->post(route('employees.reset-device', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');

    $employee->refresh();
    expect($employee->device_fingerprint)->toBeNull()
        ->and($employee->device_name)->toBeNull()
        ->and($employee->tokens()->count())->toBe(0);

    // The new phone now registers on its next OTP login instead of getting 403.
    $verificationToken = 'b6a1f1d2-7c1e-4a51-9a0e-6f3d2c1b0a99';
    Cache::put("otp:token:{$verificationToken}", ['employee_id' => $employee->id, 'code' => '123456'], 300);

    $this->postJson(route('api.employee.auth.verify-otp'), [
        'verification_token' => $verificationToken,
        'otp' => '123456',
        'device' => ['platform' => 'ios', 'device_id' => 'new-phone', 'brand' => 'Apple', 'model' => 'iPhone 16'],
    ])->assertOk();

    expect($employee->refresh()->device_name)->toBe('Apple iPhone 16');
});

it('passes the registered device to the edit page', function () {
    $tenant = Tenant::factory()->create();
    $employee = employeeWithDevice($tenant);

    $this->actingAs(User::factory()->admin($tenant)->create())
        ->get(route('employees.edit', $employee))
        ->assertInertia(fn ($page) => $page
            ->where('employee.has_registered_device', true)
            ->where('employee.device_name', 'Samsung S24'));
});

it('forbids a user without the Employees Edit permission', function () {
    $tenant = Tenant::factory()->create();
    $employee = employeeWithDevice($tenant);
    $viewer = User::factory()->withTenant($tenant)->create();
    UserPermission::create([
        'user_id' => $viewer->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Employees->value,
        'action' => PermissionAction::View->value,
    ]);

    $this->actingAs($viewer)->post(route('employees.reset-device', $employee))->assertForbidden();

    expect($employee->refresh()->device_fingerprint)->not->toBeNull();
});

it('forbids resetting an employee of another tenant', function () {
    $employee = employeeWithDevice(Tenant::factory()->create());
    $otherTenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->admin($otherTenant)->create())
        ->post(route('employees.reset-device', $employee))
        ->assertForbidden();

    expect($employee->refresh()->tokens()->count())->toBe(1);
});
