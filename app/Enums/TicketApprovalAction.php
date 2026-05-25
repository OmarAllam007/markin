<?php

namespace App\Enums;

enum TicketApprovalAction: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Delegated = 'delegated';

    public function label(): string
    {
        return match ($this) {
            TicketApprovalAction::Pending => 'Pending',
            TicketApprovalAction::Approved => 'Approved',
            TicketApprovalAction::Rejected => 'Rejected',
            TicketApprovalAction::Returned => 'Returned',
            TicketApprovalAction::Delegated => 'Delegated',
        };
    }

    public function color(): string
    {
        return match ($this) {
            TicketApprovalAction::Pending => 'warning',
            TicketApprovalAction::Approved => 'success',
            TicketApprovalAction::Rejected => 'danger',
            TicketApprovalAction::Returned => 'info',
            TicketApprovalAction::Delegated => 'secondary',
        };
    }
}
