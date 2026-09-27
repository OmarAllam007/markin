<?php

namespace App\Models;

use App\Enums\AppModule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'module', 'is_enabled'])]
class TenantModule extends Model
{
    protected function casts(): array
    {
        return [
            'module' => AppModule::class,
            'is_enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
