<?php

namespace App\Enums;

enum TicketLogType: string
{
    case Created = 'created';
    case RuleApplied = 'rule_applied';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ApprovalRequested = 'approval_requested';
    case ApprovalRemoved = 'approval_removed';
    case ApprovalSkipped = 'approval_skipped';
    case ApprovalApproved = 'approval_approved';
    case ApprovalRejected = 'approval_rejected';
    case MessageAdded = 'message_added';
    case AttachmentAdded = 'attachment_added';
    case AttachmentRemoved = 'attachment_removed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Ticket created',
            self::RuleApplied => 'Assigned by business rule',
            self::Updated => 'Ticket updated',
            self::Deleted => 'Ticket deleted',
            self::Restored => 'Ticket restored',
            self::ApprovalRequested => 'Approval requested',
            self::ApprovalRemoved => 'Approval removed',
            self::ApprovalSkipped => 'Approval step skipped',
            self::ApprovalApproved => 'Approved',
            self::ApprovalRejected => 'Rejected',
            self::MessageAdded => 'Message',
            self::AttachmentAdded => 'File attached',
            self::AttachmentRemoved => 'File removed',
        };
    }
}
