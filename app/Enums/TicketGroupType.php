<?php

namespace App\Enums;

enum TicketGroupType: string
{
    case Technician = 'technician';
    case Approver = 'approver';

    public function label(): string
    {
        return match ($this) {
            TicketGroupType::Technician => 'Technician Group',
            TicketGroupType::Approver => 'Approver Group',
        };
    }
}
