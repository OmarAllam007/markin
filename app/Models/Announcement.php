<?php

namespace App\Models;

use App\Enums\AnnouncementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'created_by',
    'type',
    'title',
    'description',
    'target_type',
    'target_location_id',
    'target_department_id',
    'target_employee_ids',
    'attachment_path',
    'recipients_count',
    'sent_at',
])]
class Announcement extends Model
{
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'target_employee_ids' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'target_location_id');
    }

    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }
}
