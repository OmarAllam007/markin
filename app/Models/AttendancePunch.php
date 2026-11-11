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
    'note',
    'gps_accuracy',
    'is_mocked',
    'is_rooted',
    'is_emulator',
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
            'gps_accuracy' => 'decimal:2',
            'is_mocked' => 'boolean',
            'is_rooted' => 'boolean',
            'is_emulator' => 'boolean',
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
