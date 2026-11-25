<?php

use App\Enums\AppModule;
use App\Mail\TicketApprovalRespondedMail;
use App\Models\ApprovalRole;
use App\Models\Employee;
use App\Models\RequestStatus;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Services\TicketApprovalService;
use App\Services\TicketMessageService;
use App\Services\TicketService;
use App\Services\TicketSlaClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $boss = fn ($name) => ApprovalRole::create(['tenant_id' => $this->tenant->id, 'name' => $name,
        'employee_id' => Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => strtolower($name).'@example.com'])->id]);
    $this->service = configuredTicketService($this->tenant, ['approval_steps' => ["role:{$boss('Manager')->id}", "role:{$boss('Director')->id}"]]);
    $this->status = fn (string $name) => RequestStatus::where('tenant_id', $this->tenant->id)->where('name', $name)->sole();
    $this->ticket = app(TicketService::class)->create(
        ['subject' => 'Laptop', 'category_id' => $this->service['category']->id, 'subcategory_id' => $this->service['subcategory']->id],
        Employee::factory()->create(['tenant_id' => $this->tenant->id]),
        $this->tenant->id,
    )->fresh();
    $this->answer = fn (int $stage, bool $approve) => app(TicketApprovalService::class)
        ->respond($this->ticket->approvals()->where('stage_order', $stage)->sole(), $approve, $approve ? null : 'Over budget');
});

it('waits for approval while approvals are pending and opens once all approve', function () {
    expect($this->ticket->requestStatus->name)->toBe('Waiting for approval')
        ->and(TicketSlaClock::state($this->ticket))->toBe('approval');

    ($this->answer)(1, true);
    expect($this->ticket->fresh()->requestStatus->name)->toBe('Waiting for approval');

    ($this->answer)(2, true);
    $ticket = $this->ticket->fresh();
    expect($ticket->requestStatus->name)->toBe('Open')
        ->and($ticket->status->value)->toBe('approved')
        ->and(TicketSlaClock::state($ticket))->toBe('running');

    // Every answer reaches the technician.
    Mail::assertQueued(TicketApprovalRespondedMail::class, 2);
});

it('opens the request when anyone rejects, keeping the approval outcome', function () {
    ($this->answer)(1, false);

    $ticket = $this->ticket->fresh();
    expect($ticket->requestStatus->name)->toBe('Open')
        ->and($ticket->status->value)->toBe('rejected');
    Mail::assertQueued(TicketApprovalRespondedMail::class, fn ($m) => $m->hasTo($this->service['technician']->email));
});

it('never moves a request the technician resolved or closed', function () {
    app(TicketMessageService::class)->post($this->ticket, $this->service['technician'], false, [
        'body' => 'Handled another way', 'request_status_id' => ($this->status)('Closed')->id,
    ]);

    ($this->answer)(1, true);
    ($this->answer)(2, true);

    expect($this->ticket->fresh()->requestStatus->name)->toBe('Closed')
        ->and($this->ticket->fresh()->status->value)->toBe('approved');
});

it('keeps the automatic status out of every manual picker', function () {
    $waiting = ($this->status)('Waiting for approval');

    expect(TicketMessageService::statusesFor($this->tenant->id, false)->pluck('id'))->not->toContain($waiting->id)
        ->and(TicketMessageService::statusesFor($this->tenant->id, true)->pluck('id'))->not->toContain($waiting->id);

    $this->actingAs($this->service['technician'])
        ->post(route('ticketing.tickets.messages.store', $this->ticket), ['body' => 'x', 'request_status_id' => $waiting->id])
        ->assertSessionHasErrors('request_status_id');
});

it('lists requests waiting for approval among active ones', function () {
    $this->actingAs($this->service['technician']);
    $page = fn (string $filter) => $this->get(route('ticketing.tickets.index', ['request_status_id' => $filter]))->viewData('page')['props']['tickets']['total'];

    expect($page('active'))->toBe(1)->and($page('awaiting_approval'))->toBe(1)->and($page('open'))->toBe(0);
});
