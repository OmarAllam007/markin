<?php

namespace App\Models;

use App\Enums\AttendancePunchLogStatus;
use App\Enums\AttendanceSource;
use App\Enums\PunchType;
use Database\Factories\AttendancePunchLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'employee_id',
    'source',
    'punch_type',
    'status',
    'failure_reason',
    'attendance_punch_id',
    'latitude',
    'longitude',
    'ip_address',
    'device_name',
    'user_agent',
    'attempted_at',
])]
class AttendancePunchLog extends Model
{
    /** @use HasFactory<AttendancePunchLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'source' => AttendanceSource::class,
            'punch_type' => PunchType::class,
            'status' => AttendancePunchLogStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'attempted_at' => 'datetime',
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

    public function punch(): BelongsTo
    {
        return $this->belongsTo(AttendancePunch::class, 'attendance_punch_id');
    }
}
