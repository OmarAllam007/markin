<?php

namespace App\Models;

use App\Enums\TicketApprovalAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'ticket_id',
    'stage_order',
    'approver_user_id',
    'approver_employee_id',
    'requested_by_user_id',
    'description',
    'role',
    'action',
    'comments',
    'delegated_to_user_id',
    'acted_at',
    'notified_at',
])]
class TicketApproval extends Model
{
    protected function casts(): array
    {
        return [
            'action' => TicketApprovalAction::class,
            'acted_at' => 'datetime',
            'notified_at' => 'datetime',
            'stage_order' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function delegatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to_user_id');
    }

    public function approverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** Sent to the approver with the request email. */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
