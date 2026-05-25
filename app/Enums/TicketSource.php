<?php

namespace App\Enums;

enum TicketSource: string
{
    case Web = 'web';
    case Mobile = 'mobile';
    case Email = 'email';
    case Api = 'api';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            TicketSource::Web => 'Web',
            TicketSource::Mobile => 'Mobile',
            TicketSource::Email => 'Email',
            TicketSource::Api => 'API',
            TicketSource::Manual => 'Manual',
        };
    }
}
