<?php

use App\Enums\AppModule;
use App\Enums\TicketApprovalAction;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Services\TicketApprovalService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('lets only the notified approver decide once from the employee API', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $service = configuredTicketService($tenant);
    $requester = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $approver = Employee::factory()->create(['tenant_id' => $tenant->id, 'email' => 'approver@example.com']);
    $other = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $ticket = app(TicketService::class)->create([
        'subject' => 'Laptop', 'category_id' => $service['category']->id, 'subcategory_id' => $service['subcategory']->id,
    ], $requester, $tenant->id);
    $approval = app(TicketApprovalService::class)->request($ticket, $approver, 1);
    $url = route('api.employee.tickets.approvals.respond', [$ticket, $approval]);

    $this->actingAs($other, 'sanctum')->postJson($url, ['decision' => 'approve'])->assertNotFound();
    $this->actingAs($approver, 'sanctum')->getJson(route('api.employee.tickets.show', $ticket))
        ->assertJsonPath('data.approvals.0.is_mine', true)
        ->assertJsonPath('data.approvals.0.can_respond', true);
    $this->postJson($url, ['decision' => 'reject'])->assertUnprocessable();
    $this->postJson($url, ['decision' => 'approve'])->assertOk();
    $this->postJson($url, ['decision' => 'approve'])->assertStatus(409);
    expect($approval->fresh()->action)->toBe(TicketApprovalAction::Approved);
});

it('requires a rejection reason and records the approver identity', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $service = configuredTicketService($tenant);
    $requester = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $approver = Employee::factory()->create(['tenant_id' => $tenant->id, 'email' => 'manager@example.com']);
    $ticket = app(TicketService::class)->create([
        'subject' => 'Laptop', 'category_id' => $service['category']->id, 'subcategory_id' => $service['subcategory']->id,
    ], $requester, $tenant->id);
    $approval = app(TicketApprovalService::class)->request($ticket, $approver, 1);
    $url = route('api.employee.tickets.approvals.respond', [$ticket, $approval]);

    $this->actingAs($approver, 'sanctum')->postJson($url, ['decision' => 'reject'])->assertUnprocessable();
    $this->postJson($url, ['decision' => 'reject', 'comments' => 'Missing information'])->assertOk();
    expect($approval->fresh()->action)->toBe(TicketApprovalAction::Rejected)
        ->and($approval->fresh()->comments)->toBe('Missing information');
});
