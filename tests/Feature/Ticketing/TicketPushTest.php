<?php

use App\Enums\AppModule;
use App\Enums\NotificationType;
use App\Models\ApprovalRole;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Services\TicketApprovalService;
use App\Services\TicketMessageService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $this->approver = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'boss@example.com']);
    $role = ApprovalRole::create(['tenant_id' => $this->tenant->id, 'name' => 'Manager', 'employee_id' => $this->approver->id]);
    $this->service = configuredTicketService($this->tenant, ['approval_steps' => ["role:{$role->id}"]]);
    // The technician's own employee record, matched by email.
    $this->techEmployee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => $this->service['technician']->email]);
    $this->requester = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->ticket = app(TicketService::class)->create(
        ['subject' => 'Laptop', 'category_id' => $this->service['category']->id, 'subcategory_id' => $this->service['subcategory']->id],
        $this->requester,
        $this->tenant->id,
    )->fresh();
    $this->pushes = fn (Employee $to, NotificationType $type) => EmployeeNotification::where('employee_id', $to->id)->where('type', $type)->get();
});

it('pushes the approver with the ticket and approval to open', function () {
    $push = ($this->pushes)($this->approver, NotificationType::ApprovalRequested)->sole();

    expect($push->data)->toBe(['ticket_id' => $this->ticket->id, 'approval_id' => $this->ticket->approvals()->sole()->id]);
});

it('pushes the technician and the requester on every approval answer', function () {
    app(TicketApprovalService::class)->respond($this->ticket->approvals()->sole(), false, 'Over budget');

    expect(($this->pushes)($this->techEmployee, NotificationType::ApprovalResponded))->toHaveCount(1)
        ->and(($this->pushes)($this->requester, NotificationType::ApprovalResponded)->sole()->body)->toContain('rejected');
});

it('pushes the requester when support replies, not when they reply themselves', function () {
    $messages = app(TicketMessageService::class);
    $messages->post($this->ticket, $this->requester, true, ['body' => 'Any news?']);
    $messages->post($this->ticket, $this->service['technician'], false, ['body' => 'On it']);

    expect(($this->pushes)($this->requester, NotificationType::TicketReply)->sole()->data)->toBe(['ticket_id' => $this->ticket->id]);
});

it('lets the approver open the ticket on the phone but not reply', function () {
    $this->actingAs($this->approver, 'sanctum')
        ->getJson(route('api.employee.tickets.show', $this->ticket))
        ->assertOk()
        ->assertJsonPath('data.is_requester', false);

    $this->actingAs($this->approver, 'sanctum')
        ->postJson(route('api.employee.tickets.messages.store', $this->ticket), ['body' => 'hi'])
        ->assertNotFound();
});
