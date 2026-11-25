<?php

namespace App\Mail;

use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly TicketMessage $message) {}

    public function envelope(): Envelope
    {
        $ticket = $this->message->ticket;
        $employeeId = $ticket->employee?->employee_number;

        return new Envelope(subject: "Request #{$ticket->id}".($employeeId ? " · Employee {$employeeId}" : '').' | New message');
    }

    public function content(): Content
    {
        $this->message->loadMissing(['user:id,name', 'employee:id,english_name,employee_number', 'requestStatus:id,name', 'ticket.employee:id,english_name,employee_number', 'ticket.requester:id,name', 'ticket.category:id,name', 'ticket.subcategory:id,name', 'ticket.item:id,name']);

        return new Content(view: 'mail.ticket-message', with: ['ticketMessage' => $this->message, 'ticket' => $this->message->ticket]);
    }

    public function attachments(): array
    {
        return $this->message->attachments
            ->map(fn ($a) => Attachment::fromStorageDisk('local', $a->file_path)->as($a->file_name)->withMime($a->mime_type))
            ->all();
    }
}
