<?php

namespace App\Models;

use Database\Factories\ZkMachineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'location_id',
    'serial_number',
    'name',
    'secret_token',
    'firmware_version',
    'platform',
    'last_sync_at',
    'last_attlog_stamp',
])]
class ZkMachine extends Model
{
    /** @use HasFactory<ZkMachineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'last_attlog_stamp' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function rawLogs(): HasMany
    {
        return $this->hasMany(ZkRawLog::class);
    }

    public function pendingLogs(): HasMany
    {
        return $this->hasMany(ZkRawLog::class)->whereNull('processed_at');
    }
}
