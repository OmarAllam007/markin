<?php

namespace App\Enums;

/** What a request status means for timing and for who may set it. */
enum RequestStatusType: string
{
    case Open = 'open';
    case OnHold = 'on_hold';
    /** Set and cleared by the approval flow only. */
    case AwaitingApproval = 'awaiting_approval';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open — time counts',
            self::OnHold => 'On hold — time paused',
            self::AwaitingApproval => 'Awaiting approval — time paused, set automatically',
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

    /** Nobody picks it by hand: the approval flow moves requests in and out of it. */
    public function manual(): bool
    {
        return $this !== self::AwaitingApproval;
    }

    /** The request is finished; approvals can no longer move it. */
    public function finished(): bool
    {
        return in_array($this, [self::Resolved, self::Closed, self::Cancelled], true);
    }
}
