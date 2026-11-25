<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the requester (confirmation) and to the assigned technician (new work). */
class TicketCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly bool $forTechnician,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->forTechnician
                ? "New ticket assigned to you: #{$this->ticket->id} {$this->ticket->subject}"
                : "Your request #{$this->ticket->id} has been received",
        );
    }

    public function content(): Content
    {
        $this->ticket->loadMissing(['requester:id,name', 'employee:id,english_name', 'technician:id,name', 'group:id,name', 'category:id,name', 'subcategory:id,name', 'item:id,name', 'sla:id,name']);

        return new Content(
            markdown: 'mail.ticket-created',
            with: ['ticket' => $this->ticket, 'forTechnician' => $this->forTechnician],
        );
    }
}
