<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'name',
    'first_response_hours',
    'resolve_hours',
    'business_hours_only',
    'is_active',
])]
class TicketSla extends Model
{
    protected function casts(): array
    {
        return [
            'business_hours_only' => 'boolean',
            'is_active' => 'boolean',
            'first_response_hours' => 'integer',
            'resolve_hours' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'sla_id');
    }
}
