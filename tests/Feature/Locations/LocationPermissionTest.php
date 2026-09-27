<?php

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function grantLocationPermission(User $user, Tenant $tenant, PermissionAction $action): void
{
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Locations->value,
        'action' => $action->value,
    ]);
}

function locationPayload(): array
{
    return [
        'name' => 'Riyadh Branch',
        'coordinates' => ['north' => 24.8, 'south' => 24.6, 'east' => 46.8, 'west' => 46.6],
    ];
}

// --- index ---

it('blocks a non-admin without the View permission from the locations list', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->get(route('locations.index'))->assertForbidden();
});

it('allows a non-admin with the View permission to see the locations list', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantLocationPermission($user, $tenant, PermissionAction::View);

    $this->actingAs($user)->get(route('locations.index'))->assertOk();
});

// --- store ---

it('blocks a non-admin without the Create permission from creating a location', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->post(route('locations.store'), locationPayload())->assertForbidden();
    expect(Location::count())->toBe(0);
});

it('allows a non-admin with the Create permission to create a location', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    grantLocationPermission($user, $tenant, PermissionAction::Create);

    $this->actingAs($user)->post(route('locations.store'), locationPayload())
        ->assertRedirect(route('locations.index'));
    expect(Location::count())->toBe(1);
});

// --- destroy ---

it('blocks a non-admin without the Delete permission from deleting a location', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant)->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)->delete(route('locations.destroy', $location))->assertForbidden();
    expect($location->fresh())->not->toBeNull();
});

// --- admin bypass ---

it('allows a tenant admin to manage locations regardless of granted permissions', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('locations.index'))->assertOk();
    $this->actingAs($admin)->post(route('locations.store'), locationPayload())
        ->assertRedirect(route('locations.index'));
});
