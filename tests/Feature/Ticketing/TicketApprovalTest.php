<?php

use App\Enums\AppModule;
use App\Mail\TicketApprovalRequestMail;
use App\Models\ApprovalRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\User;
use App\Services\TicketApprovalService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    $this->tenant = Tenant::factory()->create();
    TenantModule::create(['tenant_id' => $this->tenant->id, 'module' => AppModule::Ticketing->value, 'is_enabled' => true]);
    $this->admin = User::factory()->admin($this->tenant)->create();
    $this->service = configuredTicketService($this->tenant);
    $this->requester = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E1']);
    $this->manager = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E2', 'email' => 'manager@example.com']);
    $this->director = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E3', 'email' => 'director@example.com']);
});

function newTicket(): Ticket
{
    $s = test()->service;

    return app(TicketService::class)->create(
        ['subject' => 'Laptop', 'category_id' => $s['category']->id, 'subcategory_id' => $s['subcategory']->id],
        test()->requester,
        test()->tenant->id,
    );
}

function respondTo(TicketApproval $approval, string $decision, ?string $comments = null)
{
    return test()->post(URL::signedRoute('ticket-approvals.respond', $approval), ['decision' => $decision, 'comments' => $comments]);
}

it('lets the technician request approvals in stages, mailing the file and the next stage only when the previous one approves', function () {
    $ticket = newTicket();

    $this->actingAs($this->service['technician'])
        ->post(route('ticketing.tickets.approvals.store', $ticket), [
            'approver_employee_id' => $this->manager->id,
            'description' => 'Please check the quote',
            'file' => UploadedFile::fake()->create('quote.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

    // Second approval needs a stage choice; "new stage" makes stage 2.
    $this->post(route('ticketing.tickets.approvals.store', $ticket), ['approver_employee_id' => $this->director->id])
        ->assertSessionHasErrors('stage_order');
    $this->post(route('ticketing.tickets.approvals.store', $ticket), ['approver_employee_id' => $this->director->id, 'new_stage' => true])
        ->assertSessionHasNoErrors();

    [$first, $second] = $ticket->approvals()->orderBy('stage_order')->get();
    expect([$first->stage_order, $second->stage_order])->toBe([1, 2])
        ->and($ticket->fresh()->status->value)->toBe('in_review')
        ->and($second->notified_at)->toBeNull()
        ->and($first->description)->toBe('Please check the quote');
    $this->get(URL::signedRoute('ticket-approvals.show', $first))->assertSee('Please check the quote');

    Mail::assertQueued(TicketApprovalRequestMail::class, 1);
    Mail::assertQueued(TicketApprovalRequestMail::class, fn ($m) => $m->hasTo('manager@example.com') && count($m->attachments()) === 1);

    respondTo($first, 'approve')->assertRedirect();
    expect($second->fresh()->notified_at)->not->toBeNull();
    Mail::assertQueued(TicketApprovalRequestMail::class, fn ($m) => $m->hasTo('director@example.com'));

    respondTo($second, 'approve');
    expect($ticket->fresh()->status->value)->toBe('approved');
});

it('rejects the ticket on any rejection and needs a reason', function () {
    $ticket = newTicket();
    $approval = app(TicketApprovalService::class)->request($ticket, $this->manager, 1);

    respondTo($approval, 'reject')->assertSessionHasErrors('comments');
    respondTo($approval, 'reject', 'Over budget');

    expect($ticket->fresh()->status->value)->toBe('rejected')
        ->and($approval->fresh()->comments)->toBe('Over budget');
    respondTo($approval, 'approve')->assertStatus(409);
});

it('refuses unsigned links, other users, and approvers without email', function () {
    $ticket = newTicket();
    $approval = app(TicketApprovalService::class)->request($ticket, $this->manager, 1);

    $this->post(route('ticket-approvals.respond', $approval), ['decision' => 'approve'])->assertForbidden();
    $this->get(URL::signedRoute('ticket-approvals.show', $approval))->assertOk()->assertSee('Laptop');

    $stranger = User::factory()->withTenant($this->tenant)->create();
    $this->actingAs($stranger)->post(route('ticketing.tickets.approvals.store', $ticket), ['approver_employee_id' => $this->manager->id])->assertForbidden();

    $noEmail = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => null]);
    $this->actingAs($this->admin)->post(route('ticketing.tickets.approvals.store', $ticket), ['approver_employee_id' => $noEmail->id, 'new_stage' => true])
        ->assertSessionHasErrors('approver_employee_id');
});

it('maps the service approval roles to people when the request is submitted', function () {
    $deptHead = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'head@example.com']);
    $department = Department::factory()->create(['tenant_id' => $this->tenant->id, 'manager_employee_id' => $deptHead->id]);
    $this->requester->update(['department_id' => $department->id, 'direct_manager_id' => $this->manager->id]);
    $hr = ApprovalRole::create(['tenant_id' => $this->tenant->id, 'name' => 'HR manager', 'employee_id' => $this->director->id]);
    $this->service['category']->update(['approval_steps' => ['direct_manager', 'department_manager', "role:{$hr->id}"]]);

    $ticket = newTicket();

    expect($ticket->approvals()->orderBy('stage_order')->get(['stage_order', 'approver_employee_id', 'role'])->toArray())->toBe([
        ['stage_order' => 1, 'approver_employee_id' => $this->manager->id, 'role' => 'Direct manager'],
        ['stage_order' => 2, 'approver_employee_id' => $deptHead->id, 'role' => 'Department manager'],
        ['stage_order' => 3, 'approver_employee_id' => $this->director->id, 'role' => 'HR manager'],
    ])->and($ticket->fresh()->status->value)->toBe('in_review');
    Mail::assertQueued(TicketApprovalRequestMail::class, 1);
    Mail::assertQueued(TicketApprovalRequestMail::class, fn ($m) => $m->hasTo('manager@example.com'));
});

it('lets a lower level override the steps, or turn approvals off with an empty list', function () {
    $hr = ApprovalRole::create(['tenant_id' => $this->tenant->id, 'name' => 'HR manager', 'employee_id' => $this->director->id]);
    $this->service['category']->update(['approval_steps' => ['direct_manager', "role:{$hr->id}"]]);

    $this->service['subcategory']->update(['approval_steps' => ["role:{$hr->id}"]]);
    expect(newTicket()->approvals()->pluck('role')->all())->toBe(['HR manager']);

    $this->service['subcategory']->update(['approval_steps' => []]);
    $ticket = newTicket();
    expect($ticket->approvals()->count())->toBe(0)->and($ticket->status->value)->toBe('submitted');
});

it('skips steps nobody can answer and says why in the activity log', function () {
    $department = Department::factory()->create(['tenant_id' => $this->tenant->id, 'manager_employee_id' => null]);
    $this->requester->update(['department_id' => $department->id]);
    $empty = ApprovalRole::create(['tenant_id' => $this->tenant->id, 'name' => 'Finance manager']);
    $self = ApprovalRole::create(['tenant_id' => $this->tenant->id, 'name' => 'Team lead', 'employee_id' => $this->requester->id]);
    $hr = ApprovalRole::create(['tenant_id' => $this->tenant->id, 'name' => 'HR manager', 'employee_id' => $this->director->id]);
    $hr2 = ApprovalRole::create(['tenant_id' => $this->tenant->id, 'name' => 'HR director', 'employee_id' => $this->director->id]);
    $this->service['category']->update(['approval_steps' => ['direct_manager', 'department_manager', "role:{$empty->id}", "role:{$self->id}", "role:{$hr->id}", "role:{$hr2->id}"]]);

    $ticket = newTicket();

    // One person holding two roles approves at both stages.
    expect($ticket->approvals()->orderBy('stage_order')->get(['stage_order', 'role', 'approver_employee_id'])->toArray())->toBe([
        ['stage_order' => 1, 'role' => 'HR manager', 'approver_employee_id' => $this->director->id],
        ['stage_order' => 2, 'role' => 'HR director', 'approver_employee_id' => $this->director->id],
    ])
        ->and($ticket->logs()->where('type', 'approval_skipped')->orderBy('id')->get()->pluck('meta.reason')->all())->toBe([
            'the employee has no direct manager',
            "the employee's department has no manager",
            'nobody holds this role',
            'the approver is the requester',
        ]);
});

it('saves approval steps per service level and only accepts this company\'s roles', function () {
    $hr = ApprovalRole::create(['tenant_id' => $this->tenant->id, 'name' => 'HR manager']);
    $foreign = ApprovalRole::create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'HR manager']);
    $url = route('ticketing.admin.service-catalog.update', ['level' => 'category', 'id' => $this->service['category']->id]);
    $base = ['group_id' => $this->service['group']->id];

    $this->actingAs($this->admin)->put($url, [...$base, 'approval_steps' => ["role:{$foreign->id}"]])->assertSessionHasErrors('approval_steps.0');
    $this->put($url, [...$base, 'approval_steps' => ["role:{$hr->id}", 'direct_manager']])->assertSessionHasNoErrors();

    expect($this->service['category']->fresh()->approval_steps)->toBe(["role:{$hr->id}", 'direct_manager']);
});

it('manages approval roles and department managers', function () {
    $this->actingAs($this->admin)->post(route('ticketing.admin.approval-roles.store'), ['name' => 'HR manager', 'employee_id' => $this->director->id])
        ->assertSessionHasNoErrors();
    $this->post(route('ticketing.admin.approval-roles.store'), ['name' => 'HR manager'])->assertSessionHasErrors('name');

    $noEmail = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'email' => null]);
    $role = ApprovalRole::sole();
    $this->put(route('ticketing.admin.approval-roles.update', $role), ['name' => 'HR manager', 'employee_id' => $noEmail->id])
        ->assertSessionHasErrors('employee_id');

    $department = Department::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->put(route('departments.update', $department), ['name' => $department->name, 'manager_employee_id' => $this->manager->id])
        ->assertSessionHasNoErrors();
    expect($department->fresh()->manager->id)->toBe($this->manager->id);
});

it('links direct managers from the employee import by employee number', function () {
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([
        ['employee_number', 'english_name', 'arabic_name', 'mobile', 'email', 'direct_manager_number'],
        ['E10', 'New Lead', 'قائد', '+966 501110000', null, 'E2'],   // existing employee
        ['E11', 'New Member', 'عضو', '+966 501110001', null, 'e10'], // row in the same file
        ['E12', 'Orphan', 'يتيم', '+966 501110002', null, 'NOPE'],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'emp').'.xlsx';
    (new Xlsx($sheet))->save($path);

    $this->actingAs($this->admin)
        ->post(route('employees.import.store'), ['file' => new UploadedFile($path, 'employees.xlsx', null, null, true)])
        ->assertSessionHas('import_errors', ['Row 4: imported, but direct_manager_number "NOPE" was not found. Set the manager on the employee page.']);

    $byNumber = Employee::whereIn('employee_number', ['E10', 'E11', 'E12'])->pluck('direct_manager_id', 'employee_number');
    expect($byNumber->all())->toBe([
        'E10' => $this->manager->id,
        'E11' => Employee::where('employee_number', 'E10')->value('id'),
        'E12' => null,
    ]);
});

it('gives the employee form the manager options', function () {
    $this->actingAs($this->admin)->get(route('employees.edit', $this->requester))
        ->assertInertia(fn ($page) => $page->has('options.managers', 3));
});
