<?php

namespace App\Models;

use App\Enums\TicketLogType;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Services\TicketLogger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'requester_id',
    'creator_id',
    'employee_id',
    'source',
    'technician_id',
    'group_id',
    'subject',
    'description',
    'category_id',
    'subcategory_id',
    'item_id',
    'type',
    'status',
    'request_status_id',
    'priority_id',
    'sla_id',
    'due_date',
    'first_response_date',
    'resolve_date',
    'close_date',
    'time_spent',
    'overdue',
    'request_id',
    'form_data',
    'client_info',
    'submitted_at',
])]
class Ticket extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::created(fn (Ticket $t) => TicketLogger::log($t, TicketLogType::Created, meta: TicketLogger::snapshot($t)));
        static::updated(function (Ticket $t) {
            if ($changes = TicketLogger::diff($t)) {
                TicketLogger::log($t, TicketLogType::Updated, $changes);
            }
        });
        static::deleted(fn (Ticket $t) => $t->isForceDeleting() || TicketLogger::log($t, TicketLogType::Deleted));
        static::restored(fn (Ticket $t) => TicketLogger::log($t, TicketLogType::Restored));
    }

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'type' => TicketType::class,
            'source' => TicketSource::class,
            'form_data' => 'array',
            'client_info' => 'array',
            'due_date' => 'datetime',
            'first_response_date' => 'datetime',
            'resolve_date' => 'datetime',
            'close_date' => 'datetime',
            'submitted_at' => 'datetime',
            'overdue' => 'boolean',
            'time_spent' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TicketGroup::class, 'group_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(TicketSubcategory::class, 'subcategory_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(TicketItem::class, 'item_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function sla(): BelongsTo
    {
        return $this->belongsTo(TicketSla::class, 'sla_id');
    }

    public function parentRequest(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'request_id');
    }

    public function linkedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'request_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TicketApproval::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TicketLog::class);
    }

    /** Work status (open, on hold, resolved…); `status` is the approval state. */
    public function requestStatus(): BelongsTo
    {
        return $this->belongsTo(RequestStatus::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}
