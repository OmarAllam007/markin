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
        $employeeId = $this->ticket->employee?->employee_number;

        return new Envelope(subject: "Request #{$this->ticket->id}".($employeeId ? " · Employee {$employeeId}" : '').' | Overdue');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ticket-overdue', with: ['ticket' => $this->ticket->loadMissing(['technician:id,name', 'sla:id,name', 'employee:id,english_name,employee_number', 'requester:id,name', 'category:id,name', 'subcategory:id,name', 'item:id,name'])]);
    }
}
