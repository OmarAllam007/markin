<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketOverdueMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Ticket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Overdue: #{$this->ticket->id} {$this->ticket->subject}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.ticket-overdue', with: ['ticket' => $this->ticket->loadMissing(['technician:id,name', 'sla:id,name'])]);
    }
}
