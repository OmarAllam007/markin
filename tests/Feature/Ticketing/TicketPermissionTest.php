<?php

use App\Enums\AppModule;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function enableTicketingModuleFor(Tenant $tenant): void
{
    TenantModule::create([
        'tenant_id' => $tenant->id,
        'module' => AppModule::Ticketing->value,
        'is_enabled' => true,
    ]);
}

function grantTicketingPermission(User $user, Tenant $tenant, PermissionAction $action): void
{
    UserPermission::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'module' => PermissionModule::Ticketing->value,
        'action' => $action->value,
    ]);
}

function ticketFormPayload(Tenant $tenant): array
{
    $service = configuredTicketService($tenant);

    return [
        'subject' => 'Laptop not working',
        'description' => 'Details',
        'category_id' => $service['category']->id,
        'subcategory_id' => $service['subcategory']->id,
    ];
}

// --- tenant module toggle layer (unaffected by per-user permission grants) ---

it('blocks access when the tenant module is disabled, even for an admin', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('ticketing.tickets.index'))->assertRedirect(route('home'));
});

// --- per-user permission layer (only applies once the module is enabled) ---

it('blocks a non-admin without the Ticketing View permission, even with the module enabled', function () {
    $tenant = Tenant::factory()->create();
    enableTicketingModuleFor($tenant);
    $user = User::factory()->withTenant($tenant)->create();

    $this->actingAs($user)->get(route('ticketing.tickets.index'))->assertForbidden();
});

it('allows a non-admin with the Ticketing View permission once the module is enabled', function () {
    $tenant = Tenant::factory()->create();
    enableTicketingModuleFor($tenant);
    $user = User::factory()->withTenant($tenant)->create();
    grantTicketingPermission($user, $tenant, PermissionAction::View);

    $this->actingAs($user)->get(route('ticketing.tickets.index'))->assertOk();
});

it('blocks a non-admin without the Ticketing Create permission from submitting a ticket', function () {
    $tenant = Tenant::factory()->create();
    enableTicketingModuleFor($tenant);
    $user = User::factory()->withTenant($tenant)->create();
    grantTicketingPermission($user, $tenant, PermissionAction::View);

    $this->actingAs($user)->post(route('ticketing.tickets.store'), ticketFormPayload($tenant))->assertForbidden();
});

it('allows a non-admin with the Ticketing Create permission to submit a ticket', function () {
    $tenant = Tenant::factory()->create();
    enableTicketingModuleFor($tenant);
    $user = User::factory()->withTenant($tenant)->create();
    grantTicketingPermission($user, $tenant, PermissionAction::Create);

    $this->actingAs($user)->post(route('ticketing.tickets.store'), ticketFormPayload($tenant))
        ->assertRedirect();
});

it('blocks a non-admin without the Ticketing Edit permission from managing ticket categories', function () {
    $tenant = Tenant::factory()->create();
    enableTicketingModuleFor($tenant);
    $user = User::factory()->withTenant($tenant)->create();
    grantTicketingPermission($user, $tenant, PermissionAction::Create); // wrong action for admin config

    $this->actingAs($user)->get(route('ticketing.admin.categories.index'))->assertForbidden();
});

it('allows a non-admin with the Ticketing Edit permission to manage ticket categories', function () {
    $tenant = Tenant::factory()->create();
    enableTicketingModuleFor($tenant);
    $user = User::factory()->withTenant($tenant)->create();
    grantTicketingPermission($user, $tenant, PermissionAction::Edit);

    $this->actingAs($user)->get(route('ticketing.admin.categories.index'))->assertOk();
});

// --- both layers together ---

it('allows an admin through both layers once the module is enabled', function () {
    $tenant = Tenant::factory()->create();
    enableTicketingModuleFor($tenant);
    $admin = User::factory()->admin($tenant)->create();

    $this->actingAs($admin)->get(route('ticketing.tickets.index'))->assertOk();
});
