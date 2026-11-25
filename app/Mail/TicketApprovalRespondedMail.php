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

        return new Envelope(subject: "{$verb}: #{$this->approval->ticket_id} {$this->approval->ticket->subject}");
    }

    public function content(): Content
    {
        $ticket = $this->approval->ticket->loadMissing(['technician:id,name', 'requestStatus:id,name']);
        $pending = $ticket->approvals()->where('action', TicketApprovalAction::Pending)->count();

        return new Content(markdown: 'mail.ticket-approval-responded', with: [
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
