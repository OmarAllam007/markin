<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantShiftPermission(User $user, Tenant $tenant, PermissionAction $action): void
{
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Shifts->value,
        'action' => $action->value,
    ]);
}

function workShiftPayload(): array
{
    return [
        'name' => 'Morning Shift',
        'type' => 'fixed',
        'weekends' => ['friday', 'saturday'],
        'checkin_time' => '09:00',
        'checkout_time' => '17:00',
        'overtime_enabled' => false,
        'calculate_overtime_early_checkin' => false,
        'break_apply_as_overtime' => false,
    ];
}

it('blocks a non-admin without the View permission from the work shifts list', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->get(route('work-shifts.index'))->assertForbidden();
});

it('blocks a non-admin without the Create permission from creating a work shift', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->post(route('work-shifts.store'), workShiftPayload())->assertForbidden();
    expect(WorkShift::count())->toBe(0);
});

it('allows a non-admin with the Create permission to create a work shift', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantShiftPermission($user, $tenant, PermissionAction::Create);

    $this->actingAs($user)->post(route('work-shifts.store'), workShiftPayload())
        ->assertRedirect(route('work-shifts.index'));
    expect(WorkShift::count())->toBe(1);
});

it('blocks a non-admin without the Delete permission from deleting a work shift', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    $workShift = WorkShift::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->delete(route('work-shifts.destroy', $workShift))->assertForbidden();
    expect($workShift->fresh())->not->toBeNull();
});

it('allows a tenant admin to manage work shifts regardless of granted permissions', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('work-shifts.index'))->assertOk();
    $this->actingAs($admin)->post(route('work-shifts.store'), workShiftPayload())
        ->assertRedirect(route('work-shifts.index'));
});
