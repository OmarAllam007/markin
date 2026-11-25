<?php

namespace App\Enums;

enum NotificationType: string
{
    case LateCheckIn = 'late_check_in';
    case EarlyCheckOut = 'early_check_out';
    case MissingCheckout = 'missing_checkout';
    case Absent = 'absent';
    case LeaveApproved = 'leave_approved';
    case LeaveRejected = 'leave_rejected';
    case TicketReply = 'ticket_reply';
    case TicketStatusChanged = 'ticket_status_changed';
    case ApprovalRequested = 'approval_requested';
    case ApprovalResponded = 'approval_responded';

    public function label(): string
    {
        return match ($this) {
            self::LateCheckIn => 'Late Check-in',
            self::EarlyCheckOut => 'Early Check-out',
            self::MissingCheckout => 'Missing Check-out',
            self::Absent => 'Absent',
            self::LeaveApproved => 'Leave Approved',
            self::LeaveRejected => 'Leave Rejected',
            self::TicketReply => 'New reply',
            self::TicketStatusChanged => 'Status changed',
            self::ApprovalRequested => 'Approval needed',
            self::ApprovalResponded => 'Approval update',
        };
    }
}
