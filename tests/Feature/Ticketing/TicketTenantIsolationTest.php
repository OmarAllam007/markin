<?php

use App\Enums\AppModule;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function enableTicketingFor(Tenant $tenant): void
{
    TenantModule::create([
        'tenant_id' => $tenant->id,
        'module' => AppModule::Ticketing->value,
        'is_enabled' => true,
    ]);
}

function ticketForTenant(Tenant $tenant, User $creator): Ticket
{
    $category = TicketCategory::create([
        'tenant_id' => $tenant->id,
        'name' => 'IT Support',
        'color' => '#000000',
        'is_active' => true,
    ]);

    return Ticket::create([
        'tenant_id' => $tenant->id,
        'requester_id' => $creator->id,
        'creator_id' => $creator->id,
        'subject' => 'Laptop not working',
        'description' => 'Details',
        'category_id' => $category->id,
        'status' => 'submitted',
    ]);
}

it('blocks a user from viewing another tenant\'s ticket', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->admin($otherTenant)->create();
    $ticket = ticketForTenant($otherTenant, $otherUser);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    enableTicketingFor($tenant);

    $this->actingAs($user)
        ->get(route('ticketing.tickets.show', $ticket))
        ->assertForbidden();
});

it('blocks a user from updating another tenant\'s ticket', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->admin($otherTenant)->create();
    $ticket = ticketForTenant($otherTenant, $otherUser);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    enableTicketingFor($tenant);

    $this->actingAs($user)
        ->put(route('ticketing.tickets.update', $ticket), [
            'subject' => 'Hijacked',
            'description' => $ticket->description,
            'category_id' => $ticket->category_id,
        ])
        ->assertForbidden();

    expect($ticket->fresh()->subject)->toBe('Laptop not working');
});

it('blocks a user from deleting another tenant\'s ticket', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->admin($otherTenant)->create();
    $ticket = ticketForTenant($otherTenant, $otherUser);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    enableTicketingFor($tenant);

    $this->actingAs($user)
        ->delete(route('ticketing.tickets.destroy', $ticket))
        ->assertForbidden();

    expect($ticket->fresh())->not->toBeNull();
});

it('blocks a user from editing another tenant\'s ticket category', function () {
    $otherTenant = Tenant::factory()->create();
    $category = TicketCategory::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'HR',
        'color' => '#111111',
        'is_active' => true,
    ]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    enableTicketingFor($tenant);

    $this->actingAs($user)
        ->get(route('ticketing.admin.categories.edit', $category))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('ticketing.admin.categories.destroy', $category))
        ->assertForbidden();
});

it('blocks a user from editing another tenant\'s priority', function () {
    $otherTenant = Tenant::factory()->create();
    $priority = TicketPriority::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Urgent',
        'color' => '#ff0000',
        'sort_order' => 1,
    ]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    enableTicketingFor($tenant);

    $this->actingAs($user)
        ->delete(route('ticketing.admin.priorities.destroy', $priority))
        ->assertForbidden();
});

it('allows a user to view their own tenant\'s ticket', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin($tenant)->create();
    enableTicketingFor($tenant);
    $ticket = ticketForTenant($tenant, $user);

    $this->actingAs($user)
        ->get(route('ticketing.tickets.show', $ticket))
        ->assertOk();
});
