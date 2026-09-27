<?php

namespace App\Models;

use App\Enums\AppModule;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'domain', 'number_of_employees', 'parent_id'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    public function settings(): HasOne
    {
        return $this->hasOne(CompanySetting::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Tenant::class, 'parent_id');
    }

    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(TenantUser::class)
            ->withPivot(['is_admin', 'is_supervisor', 'status'])
            ->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('is_admin', true);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }

    public function hasModule(AppModule $module): bool
    {
        return $this->modules()
            ->where('module', $module->value)
            ->where('is_enabled', true)
            ->exists();
    }

    /** @return array<int, int> */
    public function descendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            array_push($ids, ...$child->descendantIds());
        }

        return $ids;
    }
}
