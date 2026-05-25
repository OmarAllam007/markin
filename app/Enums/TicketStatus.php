<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            TicketStatus::Draft => 'Draft',
            TicketStatus::Submitted => 'Submitted',
            TicketStatus::InReview => 'In Review',
            TicketStatus::Approved => 'Approved',
            TicketStatus::Rejected => 'Rejected',
            TicketStatus::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            TicketStatus::Draft => 'secondary',
            TicketStatus::Submitted => 'info',
            TicketStatus::InReview => 'warning',
            TicketStatus::Approved => 'success',
            TicketStatus::Rejected => 'danger',
            TicketStatus::Cancelled => 'dark',
        };
    }
}
