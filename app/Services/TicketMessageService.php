<?php

namespace App\Services;

use App\Enums\TicketLogType;
use App\Mail\TicketMessageMail;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\RequestStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/** The conversation between the technician side and the requester on a ticket. */
class TicketMessageService
{
    /** The requester side: the mobile employee, or the web user who raised it. */
    public static function isRequester(User|Employee $author, Ticket $ticket): bool
    {
        return $author instanceof Employee
            ? $ticket->employee_id === $author->id
            : $ticket->requester_id === $author->id;
    }

    /** @return array<string, mixed> */
    public static function rules(Ticket $ticket, bool $fromRequester): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'request_status_id' => ['nullable', 'integer', Rule::in(self::statusesFor($ticket->tenant_id, $fromRequester)->pluck('id'))],
            'cc' => ['nullable', 'array', 'max:20'],
            'cc.*' => ['integer', 'distinct', Rule::exists('employees', 'id')->where('tenant_id', $ticket->tenant_id)->whereNotNull('email')->where('email', '!=', '')],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    /** Statuses this side may pick: never the automatic approval one, and resolving is for the technician side. */
    public static function statusesFor(int $tenantId, bool $fromRequester)
    {
        return RequestStatus::forTenant($tenantId)
            ->filter(fn ($s) => $s->type->manual() && (! $fromRequester || $s->type->requesterCanSet()))
            ->values();
    }

    /**
     * @param  array{body: string, request_status_id?: ?int, cc?: ?list<int>}  $data
     * @param  list<UploadedFile>  $files
     */
    public function post(Ticket $ticket, User|Employee $author, bool $fromRequester, array $data, array $files = []): TicketMessage
    {
        $newStatusId = ($data['request_status_id'] ?? null) ?: null;
        $statusChanges = $newStatusId && $newStatusId !== $ticket->request_status_id;

        $message = DB::transaction(function () use ($ticket, $author, $data, $files, $fromRequester, $newStatusId, $statusChanges) {
            $message = $ticket->messages()->create([
                'user_id' => $author instanceof User ? $author->id : null,
                'employee_id' => $author instanceof Employee ? $author->id : null,
                'from_requester' => $fromRequester,
                'body' => $data['body'],
                'request_status_id' => $statusChanges ? $newStatusId : null,
                'cc' => array_values($data['cc'] ?? []) ?: null,
            ]);

            foreach ($files as $file) {
                Attachment::create([
                    'attachable_type' => TicketMessage::class,
                    'attachable_id' => $message->id,
                    'file_path' => $file->store('ticketing/messages', 'local'),
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => $author instanceof User ? $author->id : null,
                ]);
            }

            // The first reply from support is the first response; the status change is logged by the `updated` event.
            $ticket->update(array_filter([
                'request_status_id' => $statusChanges ? $newStatusId : null,
                'first_response_date' => ! $fromRequester && ! $ticket->first_response_date ? now() : null,
            ]));

            return $message;
        });

        TicketLogger::log($ticket, TicketLogType::MessageAdded, meta: array_filter([
            'excerpt' => mb_strimwidth($message->body, 0, 140, '…'),
            'from_requester' => $fromRequester,
            'files' => collect($files)->map->getClientOriginalName()->all(),
            'cc' => Employee::whereIn('id', $message->cc ?? [])->pluck('english_name')->all(),
        ]), actor: $author instanceof Employee ? $author : null);

        $this->notify($message->load('ticket'));

        return $message;
    }

    /** Emails the other side, plus everyone copied. The author never gets their own message. */
    private function notify(TicketMessage $message): void
    {
        $ticket = $message->ticket->loadMissing(['technician', 'employee', 'requester']);
        $authorEmail = $message->user?->email ?? $message->employee?->email;

        $to = $message->from_requester
            ? [$ticket->technician?->email]
            : [$ticket->employee?->email ?? $ticket->requester?->email];
        $cc = Employee::whereIn('id', $message->cc ?? [])->pluck('email')->all();

        $to = array_values(array_diff(array_filter($to), [$authorEmail]));
        $cc = array_values(array_diff(array_filter($cc), [$authorEmail], $to));

        if ($to || $cc) {
            Mail::to($to ?: $cc)->cc($to ? $cc : [])->queue(new TicketMessageMail($message));
        }
    }
}
