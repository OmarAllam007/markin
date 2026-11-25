<?php

namespace App\Models;

use App\Enums\TicketGroupType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'name',
    'type',
    'description',
    'is_active',
])]
class TicketGroup extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => TicketGroupType::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_group_user', 'group_id', 'user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'group_id');
    }
}
