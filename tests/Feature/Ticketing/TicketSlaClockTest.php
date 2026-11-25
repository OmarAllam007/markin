<?php

use App\Enums\AppModule;
use App\Mail\TicketOverdueMail;
use App\Models\Employee;
use App\Models\RequestStatus;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TicketSla;
use App\Services\TicketMessageService;
use App\Services\TicketService;
use App\Services\TicketSlaClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->freezeTime();
    $this->tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $sla = TicketSla::create(['tenant_id' => $this->tenant->id, 'name' => '4h', 'first_response_hours' => 1, 'resolve_hours' => 4, 'is_active' => true]);
    $this->service = configuredTicketService($this->tenant, ['sla_id' => $sla->id]);
    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->ticket = app(TicketService::class)->create(
        ['subject' => 'Printer', 'category_id' => $this->service['category']->id, 'subcategory_id' => $this->service['subcategory']->id],
        $this->employee,
        $this->tenant->id,
    );
    $this->moveTo = fn (string $name) => $this->ticket->refresh()->update([
        'request_status_id' => RequestStatus::where('tenant_id', $this->tenant->id)->where('name', $name)->value('id'),
    ]);
});

it('runs while open, pauses on hold and moves the due date by the pause, stops when resolved', function () {
    $due = $this->ticket->due_date;
    expect(TicketSlaClock::state($this->ticket))->toBe('running');

    $this->travel(30)->minutes();
    ($this->moveTo)('Waiting for customer');
    expect(TicketSlaClock::state($this->ticket))->toBe('paused')
        ->and(TicketSlaClock::elapsed($this->ticket))->toBe(30);

    // Two hours waiting on the customer don't count.
    $this->travel(2)->hours();
    expect(TicketSlaClock::elapsed($this->ticket->refresh()))->toBe(30);
    ($this->moveTo)('In progress');
    expect($this->ticket->due_date->toDateTimeString())->toBe($due->addHours(2)->toDateTimeString());

    $this->travel(10)->minutes();
    ($this->moveTo)('Resolved');
    expect(TicketSlaClock::state($this->ticket))->toBe('stopped')
        ->and(TicketSlaClock::elapsed($this->ticket))->toBe(40)
        ->and($this->ticket->resolve_date->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($this->ticket->overdue)->toBeFalse();

    // Reopening starts the clock again from where it stopped.
    $this->travel(1)->hour();
    ($this->moveTo)('Open');
    $this->travel(5)->minutes();
    expect(TicketSlaClock::elapsed($this->ticket->refresh()))->toBe(45)
        ->and($this->ticket->resolve_date)->toBeNull();
});

it('flags running requests past due once, alerting the technician, but not paused ones', function () {
    $paused = $this->ticket->replicate(['request_status_id']);
    $paused->save();
    $paused->update(['request_status_id' => RequestStatus::where('tenant_id', $this->tenant->id)->where('name', 'On hold')->value('id')]);

    $this->travel(5)->hours();
    $this->artisan('tickets:check-sla')->assertSuccessful();
    $this->artisan('tickets:check-sla')->assertSuccessful();

    expect($this->ticket->fresh()->overdue)->toBeTrue()
        ->and($paused->fresh()->overdue)->toBeFalse()
        ->and($this->ticket->logs()->where('type', 'sla_breached')->count())->toBe(1);
    Mail::assertQueued(TicketOverdueMail::class, 1);
});

it('counts a late resolution as overdue', function () {
    $this->travel(5)->hours();
    ($this->moveTo)('Resolved');

    expect($this->ticket->overdue)->toBeTrue();
});

it('records the first response from support, not from the requester', function () {
    $messages = app(TicketMessageService::class);
    $this->travel(10)->minutes();
    $messages->post($this->ticket, $this->employee, true, ['body' => 'Any news?']);
    expect($this->ticket->fresh()->first_response_date)->toBeNull();

    $this->travel(15)->minutes();
    $messages->post($this->ticket->fresh(), $this->service['technician'], false, ['body' => 'On it']);
    expect($this->ticket->fresh()->first_response_date->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('shows the clock on the request page', function () {
    $this->travel(20)->minutes();

    $this->actingAs($this->service['technician'])
        ->get(route('ticketing.tickets.show', $this->ticket))
        ->assertInertia(fn ($p) => $p->where('clock.state', 'running')->where('clock.elapsed_minutes', 20)->where('clock.budget_minutes', 240));
});
