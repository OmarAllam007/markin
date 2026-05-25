<?php

namespace App\Models;

use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'ticket_type',
    'field_key',
    'label',
    'type',
    'options',
    'is_required',
    'sort_order',
])]
class TicketTypeCustomField extends Model
{
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'ticket_type' => TicketType::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
