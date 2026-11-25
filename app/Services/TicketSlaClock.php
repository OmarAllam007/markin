<?php

namespace App\Services;

use App\Enums\RequestStatusType;
use App\Models\RequestStatus;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * The resolution clock. It runs while the request status is open-type; on hold and
 * awaiting approval pause it; resolved / closed / cancelled stop it.
 * Time is counted in the SLA's calendar (working hours or around the clock).
 */
class TicketSlaClock
{
    /** Call before saving whenever the request status or the approval state changes. */
    public static function sync(Ticket $ticket): void
    {
        $now = CarbonImmutable::now();
        $type = $ticket->request_status_id ? RequestStatus::find($ticket->request_status_id)?->type : null;
        $shouldRun = $type === RequestStatusType::Open;
        $calendar = BusinessCalendar::forTicket($ticket);

        // Close the stretch that just ended.
        if ($ticket->clock_running_since && ! $shouldRun) {
            $ticket->elapsed_minutes += $calendar->minutesBetween($ticket->clock_running_since, $now);
            $ticket->clock_running_since = null;
        }

        if ($shouldRun && ! $ticket->clock_running_since) {
            if ($ticket->paused_at && $ticket->due_date) {
                $ticket->due_date = self::dueAfterPause($ticket, $calendar, $now);
            }
            $ticket->paused_at = null;
            $ticket->clock_running_since = $now;
        } elseif (in_array($type, [RequestStatusType::OnHold, RequestStatusType::AwaitingApproval], true)) {
            // Paused, not finished.
            $ticket->paused_at ??= $now;
        }

        if ($ticket->exists && $ticket->isDirty('request_status_id')) {
            self::stampOutcome($ticket, $type, $now);
        }
    }

    /** Working minutes the clock has run, up to now. */
    public static function elapsed(Ticket $ticket, ?Carbon $now = null): int
    {
        return $ticket->elapsed_minutes + ($ticket->clock_running_since
            ? BusinessCalendar::forTicket($ticket)->minutesBetween($ticket->clock_running_since, $now ?? now())
            : 0);
    }

    /** running | approval | paused | stopped */
    public static function state(Ticket $ticket): string
    {
        return match (true) {
            (bool) $ticket->clock_running_since => 'running',
            ! $ticket->paused_at => 'stopped',
            RequestStatus::find($ticket->request_status_id)?->type === RequestStatusType::AwaitingApproval => 'approval',
            default => 'paused',
        };
    }

    /** Whether a running clock is counting right now (false outside working hours). */
    public static function ticking(Ticket $ticket): bool
    {
        return $ticket->clock_running_since !== null && BusinessCalendar::forTicket($ticket)->isOpen(now());
    }

    /**
     * With an SLA, the due date is whatever working time is left from now; without one,
     * a manually set due date simply moves by the length of the pause.
     */
    private static function dueAfterPause(Ticket $ticket, BusinessCalendar $calendar, CarbonImmutable $now): CarbonImmutable
    {
        $budget = $ticket->sla ? $ticket->sla->resolve_hours * 60 : null;

        return $budget !== null
            ? $calendar->addMinutes($now, max(0, $budget - $ticket->elapsed_minutes))
            : CarbonImmutable::instance($ticket->due_date)->addSeconds((int) $ticket->paused_at->diffInSeconds($now));
    }

    private static function stampOutcome(Ticket $ticket, ?RequestStatusType $type, CarbonImmutable $now): void
    {
        if ($type === RequestStatusType::Open) {
            // Reopened: no longer resolved or closed.
            $ticket->resolve_date = null;
            $ticket->close_date = null;

            return;
        }

        if ($type?->finished()) {
            $ticket->paused_at = null;
            $ticket->resolve_date = $type === RequestStatusType::Resolved ? $now : ($ticket->resolve_date ?? $now);
            if ($type !== RequestStatusType::Resolved) {
                $ticket->close_date = $now;
            }
            // Finished late counts as overdue; finished in time clears it.
            $ticket->overdue = $ticket->due_date !== null && $now->greaterThan($ticket->due_date);
        }
    }
}
