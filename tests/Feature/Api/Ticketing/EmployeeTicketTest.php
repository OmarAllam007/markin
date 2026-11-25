<?php

use App\Enums\AppModule;
use App\Enums\TicketApprovalAction;
use App\Enums\TicketType;
use App\Mail\TicketCreatedMail;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->employee = Employee::factory()->create(['email' => 'emp@example.com']);
    $this->tenant = Tenant::find($this->employee->tenant_id);
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
});

it('lets an employee submit a ticket that is auto-assigned and emailed', function () {
    Storage::fake('local');
    $service = configuredTicketService($this->tenant);

    $this->actingAs($this->employee, 'sanctum')
        ->post(route('api.employee.tickets.store'), [
            'category_id' => $service['category']->id,
            'subcategory_id' => $service['subcategory']->id,
            'subject' => 'Screen broken',
            'attachments' => [UploadedFile::fake()->image('screen.jpg')],
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.technician', $service['technician']->name);

    $ticket = Ticket::sole();
    expect($ticket->employee_id)->toBe($this->employee->id)
        ->and($ticket->requester_id)->toBeNull()
        ->and($ticket->source->value)->toBe('mobile')
        ->and($ticket->technician_id)->toBe($service['technician']->id)
        ->and(Attachment::sole()->uploaded_by)->toBeNull();

    Mail::assertQueued(TicketCreatedMail::class, fn ($m) => ! $m->forTechnician && $m->hasTo('emp@example.com'));
    Mail::assertQueued(TicketCreatedMail::class, fn ($m) => $m->forTechnician);
});

it('returns form errors in the API shape and computes leave days', function () {
    $service = configuredTicketService($this->tenant, ['ticket_type' => TicketType::Leave->value]);
    $payload = ['category_id' => $service['category']->id, 'subcategory_id' => $service['subcategory']->id, 'subject' => 'Leave'];

    $this->actingAs($this->employee, 'sanctum')
        ->postJson(route('api.employee.tickets.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('status', false)
        ->assertJsonValidationErrors(['form_data.start_date']);

    $this->postJson(route('api.employee.tickets.store'), [...$payload, 'form_data' => [
        'start_date' => '2026-12-01', 'end_date' => '2026-12-04', 'leave_type' => 'annual', 'reason' => 'Trip',
    ]])->assertCreated();

    expect(Ticket::sole()->form_data['days_count'])->toBe(4);
});

it('lists only the employee\'s own tickets', function () {
    $service = configuredTicketService($this->tenant);
    $mine = ['category_id' => $service['category']->id, 'subcategory_id' => $service['subcategory']->id, 'subject' => 'Mine'];
    $this->actingAs($this->employee, 'sanctum')->postJson(route('api.employee.tickets.store'), $mine)->assertCreated();

    $other = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($other, 'sanctum')->postJson(route('api.employee.tickets.store'), [...$mine, 'subject' => 'Theirs'])->assertCreated();

    $this->actingAs($this->employee, 'sanctum')
        ->getJson(route('api.employee.tickets.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.tickets.data')
        ->assertJsonPath('data.tickets.data.0.subject', 'Mine');
});

it('exposes the full service tree and priorities to the app', function () {
    $service = configuredTicketService($this->tenant);
    $service['subcategory']->items()->create(['tenant_id' => $this->tenant->id, 'name' => 'Laptop']);

    $this->actingAs($this->employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.index'))
        ->assertJsonPath('data.categories.0.subcategories.0.items.0.name', 'Laptop');

    $this->getJson(route('api.employee.tickets.categories.form', $service['category']).'?subcategory_id='.$service['subcategory']->id)
        ->assertOk()
        ->assertJsonStructure(['data' => ['fields', 'priorities']]);
});

it('hides ticketing when the tenant module is off', function () {
    TenantModule::where('tenant_id', $this->tenant->id)->update(['is_enabled' => false]);

    $this->actingAs($this->employee, 'sanctum')->getJson(route('api.employee.tickets.index'))->assertForbidden();
    $this->getJson(route('api.employee.me'))->assertJsonPath('data.enabled_modules', []);
});

it('serves catalog names in every configured language', function () {
    $service = configuredTicketService($this->tenant, ['name_translations' => ['ar' => 'الدعم الفني', 'fr' => 'Support']]);
    $service['subcategory']->update(['name_translations' => ['ar' => 'أجهزة']]);

    $this->actingAs($this->employee, 'sanctum')
        ->getJson(route('api.employee.tickets.categories.index'))
        ->assertJsonPath('data.categories.0.name_translations', ['ar' => 'الدعم الفني', 'fr' => 'Support'])
        ->assertJsonPath('data.categories.0.subcategories.0.name_translations.ar', 'أجهزة');
});

it('shows an employee their own ticket with its form answers, and hides others', function () {
    $service = configuredTicketService($this->tenant, ['ticket_type' => TicketType::Leave->value]);
    $this->actingAs($this->employee, 'sanctum')->postJson(route('api.employee.tickets.store'), [
        'category_id' => $service['category']->id, 'subcategory_id' => $service['subcategory']->id, 'subject' => 'Leave',
        'form_data' => ['start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'leave_type' => 'annual', 'reason' => 'Trip'],
    ])->assertCreated();
    $ticket = Ticket::sole();
    $ticket->approvals()->create([
        'stage_order' => 1,
        'approver_employee_id' => $this->employee->id,
        'action' => TicketApprovalAction::Pending,
        'description' => 'Manager review',
    ]);

    $this->getJson(route('api.employee.tickets.show', $ticket))
        ->assertOk()
        ->assertJsonPath('data.subject', 'Leave')
        ->assertJsonPath('data.form_data.days_count', 2)
        ->assertJsonPath('data.technician', $service['technician']->name)
        ->assertJsonPath('data.logs.0.type', 'created')
        ->assertJsonPath('data.logs.0.meta.subject', 'Leave')
        ->assertJsonFragment(['key' => 'days_count']);

    $this->getJson(route('api.employee.tickets.show', $ticket))
        ->assertJsonPath('data.approvals.0.stage_order', 1)
        ->assertJsonPath('data.approvals.0.action', 'pending')
        ->assertJsonPath('data.approvals.0.description', 'Manager review');

    $other = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($other, 'sanctum')->getJson(route('api.employee.tickets.show', $ticket))->assertNotFound();
});
