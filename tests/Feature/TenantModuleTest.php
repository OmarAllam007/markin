<?php

use App\Enums\AppModule;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks access to ticketing routes when module is disabled', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    TenantModule::create([
        'tenant_id' => $tenant->id,
        'module' => AppModule::Ticketing->value,
        'is_enabled' => false,
    ]);

    $this->actingAs($user)
        ->get(route('ticketing.tickets.index'))
        ->assertRedirect(route('home'));
});

it('allows access to ticketing routes when module is enabled', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    TenantModule::create([
        'tenant_id' => $tenant->id,
        'module' => AppModule::Ticketing->value,
        'is_enabled' => true,
    ]);

    $this->actingAs($user)
        ->get(route('ticketing.tickets.index'))
        ->assertOk();
});

it('blocks access when no tenant_module record exists', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    $this->actingAs($user)
        ->get(route('ticketing.tickets.index'))
        ->assertRedirect(route('home'));
});

it('returns 403 json for api requests when module is disabled', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();

    TenantModule::create([
        'tenant_id' => $tenant->id,
        'module' => AppModule::Ticketing->value,
        'is_enabled' => false,
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->get(route('ticketing.tickets.index'))
        ->assertStatus(403);
});
