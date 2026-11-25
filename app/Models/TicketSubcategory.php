<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'category_id',
    'name',
    'name_translations',
    'description',
    'is_active',
    'group_id',
    'technician_id',
    'sla_id',
    'form_fields',
    'approval_steps',
])]
class TicketSubcategory extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'form_fields' => 'array',
            'approval_steps' => 'array',
            'name_translations' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketItem::class, 'subcategory_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'subcategory_id');
    }
}
