<?php

namespace App\Mail;

use App\Models\TicketApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/** Asks an employee to approve a ticket; the signed link needs no login. */
class TicketApprovalRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly TicketApproval $approval) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Approval needed: #{$this->approval->ticket_id} {$this->approval->ticket->subject}");
    }

    public function content(): Content
    {
        $this->approval->loadMissing(['approverEmployee:id,english_name', 'ticket.employee:id,english_name', 'ticket.requester:id,name', 'ticket.category:id,name', 'ticket.subcategory:id,name', 'ticket.item:id,name']);

        return new Content(
            markdown: 'mail.ticket-approval-request',
            with: [
                'approval' => $this->approval,
                'ticket' => $this->approval->ticket,
                'url' => URL::signedRoute('ticket-approvals.show', $this->approval),
            ],
        );
    }

    public function attachments(): array
    {
        return $this->approval->attachments
            ->map(fn ($a) => Attachment::fromStorageDisk('local', $a->file_path)->as($a->file_name)->withMime($a->mime_type))
            ->all();
    }
}
