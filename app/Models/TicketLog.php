<?php

namespace App\Models;

use App\Enums\TicketLogType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'type', 'user_id', 'employee_id', 'changes', 'meta'])]
class TicketLog extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => TicketLogType::class,
            'changes' => 'array',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
