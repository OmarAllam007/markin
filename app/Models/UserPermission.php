<?php

namespace App\Models;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'tenant_id', 'module', 'action'])]
class UserPermission extends Model
{
    protected function casts(): array
    {
        return [
            'module' => PermissionModule::class,
            'action' => PermissionAction::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
