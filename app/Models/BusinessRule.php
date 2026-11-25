<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "When the request matches these conditions, assign it to this group / technician." */
#[Fillable(['tenant_id', 'name', 'is_active', 'sort_order', 'match', 'conditions', 'group_id', 'technician_id'])]
class BusinessRule extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'conditions' => 'array', 'sort_order' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TicketGroup::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
