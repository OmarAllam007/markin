<?php

namespace App\Models;

use App\Enums\AnnouncementType;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

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

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * Scope to announcements that are visible to the given employee based on targeting rules.
     */
    public function scopeVisibleTo(Builder $query, Employee $employee): Builder
    {
        return $query
            ->where('tenant_id', $employee->tenant_id)
            ->whereNotNull('sent_at')
            ->where(function (Builder $q) use ($employee) {
                // Targeted to specific employees
                $q->where(function (Builder $q) use ($employee) {
                    $q->where('target_type', 'employees')
                        ->whereJsonContains('target_employee_ids', (string) $employee->id);
                });

                // Targeted by location / department
                $q->orWhere(function (Builder $q) use ($employee) {
                    $q->where('target_type', 'locations_departments')
                        ->where(function (Builder $q) use ($employee) {
                            // Both null = whole tenant
                            $q->where(function (Builder $q) {
                                $q->whereNull('target_location_id')
                                    ->whereNull('target_department_id');
                            });

                            if ($employee->location_id) {
                                $q->orWhere('target_location_id', $employee->location_id);
                            }

                            if ($employee->department_id) {
                                $q->orWhere('target_department_id', $employee->department_id);
                            }
                        });
                });
            });
    }
}
