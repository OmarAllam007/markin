<?php

namespace App\Models;

use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'name',
    'name_translations',
    'description',
    'icon',
    'color',
    'is_active',
    'ticket_type',
    'group_id',
    'technician_id',
    'sla_id',
    'form_fields',
    'approval_steps',
])]
class TicketCategory extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ticket_type' => TicketType::class,
            'form_fields' => 'array',
            'approval_steps' => 'array',
            'name_translations' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(TicketSubcategory::class, 'category_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }
}
