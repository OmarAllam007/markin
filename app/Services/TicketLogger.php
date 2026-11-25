<?php

namespace App\Services;

use App\Enums\TicketLogType;
use App\Enums\TicketStatus;
use App\Models\Employee;
use App\Models\RequestStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketGroup;
use App\Models\TicketItem;
use App\Models\TicketLog;
use App\Models\TicketPriority;
use App\Models\TicketSla;
use App\Models\TicketSubcategory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/** Writes the ticket's activity history. Values are stored as display text, so renames don't rewrite history. */
class TicketLogger
{
    /** Tracked columns and how to display them; anything else (timestamps, counters) is not logged. */
    private const FIELDS = [
        'subject' => null,
        'description' => null,
        'status' => 'status',
        'request_status_id' => RequestStatus::class,
        'priority_id' => TicketPriority::class,
        'technician_id' => User::class,
        'group_id' => TicketGroup::class,
        'category_id' => TicketCategory::class,
        'subcategory_id' => TicketSubcategory::class,
        'item_id' => TicketItem::class,
        'sla_id' => TicketSla::class,
        'employee_id' => Employee::class,
        'due_date' => 'date',
        'resolve_date' => 'date',
        'close_date' => 'date',
    ];

    /** The actor is whoever is logged in (web user or API employee) unless an employee is given, e.g. an approver from the email link. */
    public static function log(Ticket $ticket, TicketLogType $type, ?array $changes = null, array $meta = [], ?Employee $actor = null): TicketLog
    {
        $user = Auth::user();

        return TicketLog::create([
            'ticket_id' => $ticket->id,
            'type' => $type,
            'user_id' => ! $actor && $user instanceof User ? $user->id : null,
            'employee_id' => $actor?->id ?? ($user instanceof Employee ? $user->id : null),
            'changes' => $changes ?: null,
            'meta' => $meta ?: null,
        ]);
    }

    /** { field: { old, new } } for the save that just happened; call from the `updated` event. */
    public static function diff(Ticket $ticket): array
    {
        $changes = [];

        foreach (array_keys($ticket->getChanges()) as $key) {
            if ($key === 'form_data') {
                $old = json_decode($ticket->getRawOriginal('form_data') ?? 'null', true) ?? [];
                $new = $ticket->form_data ?? [];
                foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $k) {
                    if (($old[$k] ?? null) != ($new[$k] ?? null)) {
                        $changes["form_data.{$k}"] = ['old' => $old[$k] ?? null, 'new' => $new[$k] ?? null];
                    }
                }
            } elseif (array_key_exists($key, self::FIELDS)) {
                $changes[$key] = [
                    'old' => self::display($key, $ticket->getRawOriginal($key)),
                    'new' => self::display($key, $ticket->getAttributes()[$key]),
                ];
            }
        }

        return $changes;
    }

    /** What the ticket looked like when it was created. */
    public static function snapshot(Ticket $ticket): array
    {
        return collect(['subject', 'request_status_id', 'status', 'category_id', 'subcategory_id', 'item_id', 'priority_id', 'group_id', 'technician_id', 'sla_id', 'due_date'])
            ->mapWithKeys(fn ($key) => [$key => self::display($key, $ticket->getAttributes()[$key] ?? null)])
            ->put('source', $ticket->source?->value)
            ->filter(fn ($v) => $v !== null)
            ->all();
    }

    private static function display(string $key, mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($kind = self::FIELDS[$key]) {
            null => $raw,
            'status' => TicketStatus::tryFrom($raw)?->label() ?? $raw,
            'date' => Carbon::parse($raw)->format('Y-m-d H:i'),
            Employee::class => Employee::find($raw)?->english_name ?? "#{$raw}",
            default => $kind::find($raw)?->name ?? "#{$raw}",
        };
    }
}
