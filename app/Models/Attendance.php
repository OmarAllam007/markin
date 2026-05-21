<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'employee_id',
    'attendance_date',
    'check_in_time',
    'check_out_time',
    'worked_minutes',
    'total_late_minutes',
    'total_early_leave_minutes',
    'overtime_minutes',
    'break_minutes',
    'status',
    'shift_id',
    'scheduled_check_in',
    'scheduled_check_out',
    'attendance_source',
    'comments',
    'attachment_path',
    'location_id',
    'latitude',
    'longitude',
    'gps_accuracy',
    'address',
    'device_id',
    'ip_address',
    'user_agent',
    'approved_by',
    'approved_at',
    'created_by',
    'updated_by',
    'is_manual_edit',
    'edit_reason',
    'is_weekend',
    'is_holiday',
    'is_locked',
    'payroll_exported_at',
])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Attendance $attendance): void {
            if ($attendance->employee_id) {
                $tenantId = Employee::query()->whereKey($attendance->employee_id)->value('tenant_id');
                if ($tenantId !== null) {
                    $attendance->tenant_id = (int) $tenantId;
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_time' => 'string',
            'check_out_time' => 'string',
            'scheduled_check_in' => 'string',
            'scheduled_check_out' => 'string',
            'status' => AttendanceStatus::class,
            'attendance_source' => AttendanceSource::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'gps_accuracy' => 'decimal:2',
            'approved_at' => 'datetime',
            'payroll_exported_at' => 'datetime',
            'is_manual_edit' => 'boolean',
            'is_weekend' => 'boolean',
            'is_holiday' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }
}
