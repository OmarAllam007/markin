<?php

namespace App\Mail;

use App\Enums\TicketApprovalAction;
use App\Models\TicketApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the technician an approver answered, and where the approvals stand. */
class TicketApprovalRespondedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly TicketApproval $approval) {}

    public function envelope(): Envelope
    {
        $verb = $this->approval->action === TicketApprovalAction::Approved ? 'Approved' : 'Rejected';

        $ticket = $this->approval->ticket;
        $employeeId = $ticket->employee?->employee_number;

        return new Envelope(subject: "Request #{$ticket->id}".($employeeId ? " · Employee {$employeeId}" : '')." | {$verb}");
    }

    public function content(): Content
    {
        $ticket = $this->approval->ticket->loadMissing(['technician:id,name', 'requestStatus:id,name', 'employee:id,english_name,employee_number', 'requester:id,name', 'category:id,name', 'subcategory:id,name', 'item:id,name']);
        $pending = $ticket->approvals()->where('action', TicketApprovalAction::Pending)->count();

        return new Content(view: 'mail.ticket-approval-responded', with: [
            'approval' => $this->approval,
            'ticket' => $ticket,
            'approved' => $this->approval->action === TicketApprovalAction::Approved,
            // Rejection ends the flow even if later stages are still pending.
            'outcome' => match (true) {
                $this->approval->action === TicketApprovalAction::Rejected => 'rejected',
                $pending === 0 => 'all_approved',
                default => 'continues',
            },
            'pending' => $pending,
        ]);
    }
}
