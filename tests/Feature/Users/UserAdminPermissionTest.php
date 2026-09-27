<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantAdminUsersPermission(User $user, Tenant $tenant, PermissionAction $action): void
{
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::AdminUsers->value,
        'action' => $action->value,
    ]);
}

function newUserPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test User',
        'email' => 'newuser@example.com',
        'country_code' => '+1',
        'mobile' => '5559999999',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'is_admin' => false,
        'is_supervisor' => false,
        'preferred_theme' => 'light',
        'preferred_language' => 'en',
        'status' => UserStatus::Active->value,
    ], $overrides);
}

it('blocks a non-admin without the Create permission from creating a user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantAdminUsersPermission($user, $tenant, PermissionAction::Edit); // unrelated grant, still Forbidden

    $this->actingAs($user)->post(route('users.store'), newUserPayload())->assertForbidden();
});

it('allows a non-admin with the AdminUsers Create permission to create a user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantAdminUsersPermission($user, $tenant, PermissionAction::Create);

    $this->actingAs($user)->post(route('users.store'), newUserPayload())
        ->assertRedirect(route('users.index'));

    expect(User::where('email', 'newuser@example.com')->exists())->toBeTrue();
});

it('blocks a non-admin without the Delete permission from deleting another user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantAdminUsersPermission($user, $tenant, PermissionAction::Create);
    $target = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->delete(route('users.destroy', $target))->assertForbidden();
    expect($target->fresh())->not->toBeNull();
});

it('allows a non-admin with the Delete permission to delete another user in the same tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantAdminUsersPermission($user, $tenant, PermissionAction::Delete);
    $target = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'));

    expect(User::find($target->id))->toBeNull();
});

it('blocks deleting a user who belongs to a different tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();
    $target = User::factory()->withTenant($otherTenant)->create();

    $this->actingAs($admin)->delete(route('users.destroy', $target))->assertForbidden();
    expect($target->fresh())->not->toBeNull();
});

it('allows a tenant admin to manage users regardless of granted permissions', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->post(route('users.store'), newUserPayload())
        ->assertRedirect(route('users.index'));
});
