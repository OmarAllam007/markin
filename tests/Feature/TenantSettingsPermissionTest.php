<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function settingsPayload(): array
{
    return [
        'attendance_via' => 'all',
        'timezone' => 'Asia/Riyadh',
    ];
}

it('blocks a plain non-admin member from updating company settings', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    // Grant an unrelated permission so the user can at least authenticate/reach the tenant.
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Reports->value,
        'action' => PermissionAction::View->value,
    ]);

    $this->actingAs($user)->post(route('tenant.settings.update'), settingsPayload())->assertForbidden();
});

it('allows a non-admin with the GeneralSettings Edit permission to update company settings', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::GeneralSettings->value,
        'action' => PermissionAction::Edit->value,
    ]);

    $this->actingAs($user)->post(route('tenant.settings.update'), settingsPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

it('allows a tenant admin to update company settings regardless of granted permissions', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->post(route('tenant.settings.update'), settingsPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});
