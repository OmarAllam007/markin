<?php

namespace App\Models;

use App\Enums\ShiftType;
use Database\Factories\WorkShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'created_by',
    'name',
    'type',
    'weekends',
    'checkin_time',
    'checkout_time',
    'working_hours',
    'working_minutes',
    'limit_checkin_from',
    'limit_checkin_to',
    'overtime_enabled',
    'overtime_hours',
    'overtime_minutes',
    'calculate_overtime_early_checkin',
    'break_random_checks',
    'break_hours',
    'break_minutes',
    'break_start_from',
    'break_start_to',
    'break_apply_as_overtime',
    'allow_multiple_sessions',
    'late_checkin_grace_minutes',
    'early_checkout_grace_minutes',
])]
class WorkShift extends Model
{
    /** @use HasFactory<WorkShiftFactory> */
    use HasFactory;

    protected $attributes = [
        'weekends' => '[]',
        'overtime_enabled' => false,
        'calculate_overtime_early_checkin' => false,
        'break_apply_as_overtime' => false,
        'allow_multiple_sessions' => false,
        'late_checkin_grace_minutes' => 0,
        'early_checkout_grace_minutes' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => ShiftType::class,
            'weekends' => 'array',
            'overtime_enabled' => 'boolean',
            'calculate_overtime_early_checkin' => 'boolean',
            'break_apply_as_overtime' => 'boolean',
            'allow_multiple_sessions' => 'boolean',
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

    public function isOvernight(): bool
    {
        if (! $this->checkin_time || ! $this->checkout_time) {
            return false;
        }

        return $this->checkout_time < $this->checkin_time;
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
