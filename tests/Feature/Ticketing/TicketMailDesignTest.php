<?php

use App\Enums\TicketApprovalAction;
use App\Mail\TicketApprovalRequestMail;
use App\Mail\TicketApprovalRespondedMail;
use App\Mail\TicketCreatedMail;
use App\Mail\TicketMessageMail;
use App\Mail\TicketOverdueMail;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TicketApproval;
use App\Models\TicketMessage;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('shows the employee number and a clear action in every request email', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    $service = configuredTicketService($tenant);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'employee_number' => 'EMP-2047']);
    $ticket = app(TicketService::class)->create([
        'subject' => 'Laptop setup',
        'category_id' => $service['category']->id,
        'subcategory_id' => $service['subcategory']->id,
    ], $employee, $tenant->id);
    $approval = TicketApproval::create([
        'ticket_id' => $ticket->id,
        'stage_order' => 1,
        'approver_employee_id' => $employee->id,
        'action' => TicketApprovalAction::Approved,
    ]);
    $message = TicketMessage::create([
        'ticket_id' => $ticket->id,
        'employee_id' => $employee->id,
        'from_requester' => true,
        'body' => 'Please check the device.',
    ]);

    $approval->update(['action' => TicketApprovalAction::Rejected]);
    expect((new TicketApprovalRespondedMail($approval->fresh()))->envelope()->subject)->toContain('Rejected', 'EMP-2047');
    $approval->update(['action' => TicketApprovalAction::Approved]);

    foreach ([
        new TicketCreatedMail($ticket, false),
        new TicketCreatedMail($ticket, true),
        new TicketApprovalRequestMail($approval),
        new TicketApprovalRespondedMail($approval),
        new TicketMessageMail($message),
        new TicketOverdueMail($ticket),
    ] as $mail) {
        expect($mail->envelope()->subject)->toContain("Request #{$ticket->id}", 'EMP-2047');
        expect($mail->render())->toContain('Employee ID', 'EMP-2047', 'Laptop setup');
    }
});
