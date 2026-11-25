<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'subcategory_id',
    'name',
    'name_translations',
    'is_active',
    'group_id',
    'technician_id',
    'sla_id',
    'form_fields',
    'approval_steps',
])]
class TicketItem extends Model
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

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(TicketSubcategory::class, 'subcategory_id');
    }
}
