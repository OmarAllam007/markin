<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use App\Models\Ticket;
use App\Models\TicketApproval;

/**
 * Phone pushes about a ticket. Writing the notification row is the push (see EmployeeNotification);
 * `ticket_id` / `approval_id` in the data are what the app opens on tap.
 */
class TicketPush
{
    public static function requester(Ticket $ticket, NotificationType $type, string $body, ?TicketApproval $approval = null): void
    {
        self::send($ticket->employee_id, $ticket, $type, $body, $approval);
    }

    /**
     * Technicians are web users; they get pushes on the employee record of theirs in the
     * ticket's company, matched by email.
     * ponytail: email match, add employees.user_id if emails ever differ between the two.
     */
    public static function technician(Ticket $ticket, NotificationType $type, string $body, ?TicketApproval $approval = null): void
    {
        $email = $ticket->technician()->value('email');
        if (! $email) {
            return;
        }

        $employeeId = Employee::where('tenant_id', $ticket->tenant_id)->where('email', $email)->value('id');
        self::send($employeeId, $ticket, $type, $body, $approval);
    }

    public static function approver(TicketApproval $approval): void
    {
        $ticket = $approval->ticket;
        self::send($approval->approver_employee_id, $ticket, NotificationType::ApprovalRequested, sprintf(
            'Request #%d "%s" needs your approval (stage %d).',
            $ticket->id,
            $ticket->subject,
            $approval->stage_order,
        ), $approval);
    }

    private static function send(?int $employeeId, Ticket $ticket, NotificationType $type, string $body, ?TicketApproval $approval): void
    {
        if (! $employeeId) {
            return;
        }

        EmployeeNotification::create([
            'tenant_id' => $ticket->tenant_id,
            'employee_id' => $employeeId,
            'type' => $type,
            'title' => sprintf('%s · #%d', $type->label(), $ticket->id),
            'body' => $body,
            'data' => array_filter(['ticket_id' => $ticket->id, 'approval_id' => $approval?->id]),
        ]);
    }
}
