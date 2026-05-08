<?php

namespace App\Mail;

use App\Models\Announcement;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnouncementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Announcement $announcement,
        public readonly Employee $employee,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->announcement->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.announcement',
            with: [
                'announcement' => $this->announcement,
                'employee' => $this->employee,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->announcement->attachment_path) {
            return [
                Attachment::fromStorage($this->announcement->attachment_path),
            ];
        }

        return [];
    }
}
