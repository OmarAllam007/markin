<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantUser extends Pivot
{
    // @Todo: to understand
    public $incrementing = true;


    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'is_supervisor' => 'boolean',
            'status' => UserStatus::class,
        ];
    }
}
