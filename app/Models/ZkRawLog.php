<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'zk_machine_id',
    'employee_code',
    'punched_at',
    'punch_status',
    'verify_type',
    'raw_line',
    'processed_at',
    'error_message',
])]
class ZkRawLog extends Model
{
    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'processed_at' => 'datetime',
            'punch_status' => 'integer',
            'verify_type' => 'integer',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ZkMachine::class, 'zk_machine_id');
    }
}
