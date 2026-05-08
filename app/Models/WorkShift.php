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
    ];

    protected function casts(): array
    {
        return [
            'type' => ShiftType::class,
            'weekends' => 'array',
            'overtime_enabled' => 'boolean',
            'calculate_overtime_early_checkin' => 'boolean',
            'break_apply_as_overtime' => 'boolean',
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

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
