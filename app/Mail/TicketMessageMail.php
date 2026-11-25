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
        return new Envelope(subject: "Re: #{$this->message->ticket_id} {$this->message->ticket->subject}");
    }

    public function content(): Content
    {
        $this->message->loadMissing(['user:id,name', 'employee:id,english_name', 'requestStatus:id,name']);

        return new Content(markdown: 'mail.ticket-message', with: ['message' => $this->message, 'ticket' => $this->message->ticket]);
    }

    public function attachments(): array
    {
        return $this->message->attachments
            ->map(fn ($a) => Attachment::fromStorageDisk('local', $a->file_path)->as($a->file_name)->withMime($a->mime_type))
            ->all();
    }
}
