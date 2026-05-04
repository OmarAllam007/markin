<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'country_code', 'mobile', 'password', 'current_tenant_id', 'preferred_theme', 'preferred_language'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->using(TenantUser::class)
            ->withPivot(['is_admin', 'is_supervisor', 'status'])
            ->withTimestamps();
    }

    public function isAdminOf(Tenant $tenant): bool
    {
        return $this->tenants()
            ->wherePivot('is_admin', true)
            ->where('tenants.id', $tenant->id)
            ->exists();
    }

    public function canAccessTenant(Tenant $tenant): bool
    {
        if ($this->isAdminOf($tenant)) {
            return true;
        }

        $ancestor = $tenant->parent;
        while ($ancestor !== null) {
            if ($this->isAdminOf($ancestor)) {
                return true;
            }
            $ancestor = $ancestor->parent;
        }

        return false;
    }

    /** @return Collection<int, Tenant> */
    public function switchableTenants(): Collection
    {
        $adminTenants = $this->tenants()
            ->wherePivot('is_admin', true)
            ->with('descendants')
            ->get();

        $allIds = $adminTenants->pluck('id')->toArray();

        foreach ($adminTenants as $tenant) {
            array_push($allIds, ...$tenant->descendantIds());
        }

        return Tenant::whereIn('id', array_unique($allIds))->orderBy('name')->get();
    }
}
