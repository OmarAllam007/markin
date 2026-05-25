<?php

namespace App\Models;

use App\Enums\TicketApprovalAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id',
    'stage_order',
    'approver_user_id',
    'action',
    'comments',
    'delegated_to_user_id',
    'acted_at',
])]
class TicketApproval extends Model
{
    protected function casts(): array
    {
        return [
            'action' => TicketApprovalAction::class,
            'acted_at' => 'datetime',
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
}
