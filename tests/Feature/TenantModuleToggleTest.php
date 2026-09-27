<?php

use App\Enums\AppModule;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a tenant admin to enable a module', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->post(route('tenant.modules.update'), [
            'module' => AppModule::Ticketing->value,
            'is_enabled' => true,
        ])
        ->assertRedirect();

    expect(
        TenantModule::where('tenant_id', $tenant->id)
            ->where('module', AppModule::Ticketing->value)
            ->where('is_enabled', true)
            ->exists()
    )->toBeTrue();
});

it('allows a tenant admin to disable a module', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    TenantModule::create([
        'tenant_id' => $tenant->id,
        'module' => AppModule::Ticketing->value,
        'is_enabled' => true,
    ]);

    $this->actingAs($user)
        ->post(route('tenant.modules.update'), [
            'module' => AppModule::Ticketing->value,
            'is_enabled' => false,
        ])
        ->assertRedirect();

    expect(
        TenantModule::where('tenant_id', $tenant->id)
            ->where('module', AppModule::Ticketing->value)
            ->where('is_enabled', false)
            ->exists()
    )->toBeTrue();
});

it('rejects module toggle from a non-admin user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->withTenant($tenant, isAdmin: false)->create();

    $this->actingAs($user)
        ->post(route('tenant.modules.update'), [
            'module' => AppModule::Ticketing->value,
            'is_enabled' => true,
        ])
        ->assertForbidden();
});

it('rejects an invalid module value', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->post(route('tenant.modules.update'), [
            'module' => 'invalid_module',
            'is_enabled' => true,
        ])
        ->assertSessionHasErrors('module');
});
