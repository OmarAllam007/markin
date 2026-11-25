<?php

use App\Enums\AppModule;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\Ticket;
use App\Models\TicketLog;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    $this->tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $this->admin = User::factory()->admin($this->tenant)->create(['name' => 'Admin']);
    $this->service = configuredTicketService($this->tenant);
});

function logTypes(Ticket $ticket): array
{
    return $ticket->logs()->orderBy('id')->pluck('type')->map->value->all();
}

it('logs creation with its actor and starting values, then each edit as old → new display values', function () {
    $this->actingAs($this->admin)->post(route('ticketing.tickets.store'), [
        'subject' => 'Printer', 'category_id' => $this->service['category']->id, 'subcategory_id' => $this->service['subcategory']->id,
    ]);
    $ticket = Ticket::sole();

    $created = $ticket->logs()->sole();
    expect($created->user_id)->toBe($this->admin->id)
        ->and($created->meta)->toMatchArray(['subject' => 'Printer', 'category_id' => 'IT Support', 'technician_id' => $this->service['technician']->name]);

    $other = User::factory()->withTenant($this->tenant)->create(['name' => 'Sara']);
    $this->service['group']->members()->attach($other);

    $this->put(route('ticketing.tickets.update', $ticket), [
        'subject' => 'Printer on floor 2', 'category_id' => $ticket->category_id, 'subcategory_id' => $ticket->subcategory_id,
        'group_id' => $ticket->group_id, 'technician_id' => $other->id, 'status' => 'in_review',
    ])->assertSessionHasNoErrors();

    $update = TicketLog::where('type', 'updated')->sole();
    expect($update->changes)->toEqualCanonicalizing([
        'subject' => ['old' => 'Printer', 'new' => 'Printer on floor 2'],
        'technician_id' => ['old' => $this->service['technician']->name, 'new' => 'Sara'],
        'status' => ['old' => 'Submitted', 'new' => 'In Review'],
    ]);

    // Saving with no real change writes nothing.
    $ticket->fresh()->update(['subject' => 'Printer on floor 2']);
    expect(TicketLog::where('type', 'updated')->count())->toBe(1);

    $this->get(route('ticketing.tickets.show', $ticket))->assertInertia(fn ($page) => $page->has('logs', 2));
});

it('logs approvals — requested, approved by the employee from the link, removed — and the status changes they cause', function () {
    $ticket = app(TicketService::class)->create(
        ['subject' => 'Laptop', 'category_id' => $this->service['category']->id, 'subcategory_id' => $this->service['subcategory']->id],
        $this->admin,
        $this->tenant->id,
    );
    $manager = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'english_name' => 'Mona', 'email' => 'mona@example.com']);
    $director = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'english_name' => 'Dina', 'email' => 'dina@example.com']);

    $this->actingAs($this->admin)->post(route('ticketing.tickets.approvals.store', $ticket), [
        'approver_employee_id' => $manager->id, 'description' => 'Check budget', 'file' => UploadedFile::fake()->create('quote.pdf', 5),
    ]);
    $this->post(route('ticketing.tickets.approvals.store', $ticket), ['approver_employee_id' => $director->id, 'new_stage' => true]);

    $requested = TicketLog::where('type', 'approval_requested')->first();
    expect($requested->meta)->toMatchArray(['approver' => 'Mona', 'stage' => 1, 'description' => 'Check budget', 'file_name' => 'quote.pdf']);

    auth()->logout();
    $first = $ticket->approvals()->where('stage_order', 1)->sole();
    $this->post(URL::signedRoute('ticket-approvals.respond', $first), ['decision' => 'approve', 'comments' => 'OK']);
    $approved = TicketLog::where('type', 'approval_approved')->sole();
    expect($approved->employee_id)->toBe($manager->id)->and($approved->user_id)->toBeNull()->and($approved->meta['comments'])->toBe('OK');

    // Removing the last pending approval completes the flow.
    $this->actingAs($this->admin)->delete(route('ticketing.tickets.approvals.destroy', [$ticket, $ticket->approvals()->where('stage_order', 2)->sole()]))
        ->assertSessionHasNoErrors();

    expect(logTypes($ticket))->toBe(['created', 'approval_requested', 'updated', 'approval_requested', 'approval_approved', 'approval_removed', 'updated'])
        ->and(TicketLog::where('type', 'updated')->get()->pluck('changes.status.new')->all())->toBe(['In Review', 'Approved'])
        ->and($ticket->fresh()->status->value)->toBe('approved');

    // Answered approvals stay as history.
    $this->delete(route('ticketing.tickets.approvals.destroy', [$ticket, $first]))->assertStatus(422);
});

it('logs ticket files and the mobile employee as actor', function () {
    $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($employee, 'sanctum')->post(route('api.employee.tickets.store'), [
        'category_id' => $this->service['category']->id, 'subcategory_id' => $this->service['subcategory']->id,
        'subject' => 'Screen', 'attachments' => [UploadedFile::fake()->image('screen.jpg')],
    ], ['Accept' => 'application/json'])->assertCreated();

    $ticket = Ticket::sole();
    expect(logTypes($ticket))->toBe(['created', 'attachment_added'])
        ->and($ticket->logs()->pluck('employee_id')->unique()->all())->toBe([$employee->id]);

    Attachment::sole()->delete();
    expect($ticket->logs()->latest('id')->first()->meta)->toBe(['file_name' => 'screen.jpg']);
});
