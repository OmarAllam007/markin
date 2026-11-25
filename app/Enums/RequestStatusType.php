<?php

namespace App\Enums;

/** What a request status means for timing and for who may set it. */
enum RequestStatusType: string
{
    case Open = 'open';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open — time counts',
            self::OnHold => 'On hold — time paused',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Only the technician side can mark a request resolved. */
    public function requesterCanSet(): bool
    {
        return $this !== self::Resolved;
    }
}
