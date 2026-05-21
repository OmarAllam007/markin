<?php

namespace App\Models;

use App\Enums\AttendanceSource;
use App\Enums\PunchType;
use Database\Factories\AttendancePunchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'attendance_id',
    'type',
    'punched_at',
    'source',
    'location_id',
    'latitude',
    'longitude',
    'device_name',
])]
class AttendancePunch extends Model
{
    /** @use HasFactory<AttendancePunchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PunchType::class,
            'source' => AttendanceSource::class,
            'punched_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
