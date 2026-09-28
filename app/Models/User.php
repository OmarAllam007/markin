<?php

namespace App\Models;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'country_code', 'mobile', 'password', 'current_tenant_id', 'preferred_theme', 'preferred_language'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
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

        // A non-admin member only gets in once they've been granted at least
        // one explicit permission for this tenant — the web portal is for
        // admins and permitted staff; everyone else uses the mobile app.
        return $this->isActiveMemberOf($tenant) && $this->permissions()->where('tenant_id', $tenant->id)->exists();
    }

    public function isActiveMemberOf(Tenant $tenant): bool
    {
        return $this->tenants()
            ->where('tenants.id', $tenant->id)
            ->wherePivot('status', UserStatus::Active->value)
            ->exists();
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

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function locationAccess(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'user_location_access')
            ->withPivot('tenant_id');
    }

    public function departmentAccess(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_department_access')
            ->withPivot('tenant_id');
    }

    public function hasPermission(string $module, string $action, int $tenantId): bool
    {
        return $this->permissions()
            ->where('tenant_id', $tenantId)
            ->where('module', $module)
            ->where('action', $action)
            ->exists();
    }

    /**
     * Whether the user may perform the given action, either as a tenant admin
     * (who bypasses all granular checks) or via an explicit UserPermission grant.
     */
    public function canPerform(Tenant $tenant, PermissionModule|string $module, PermissionAction|string $action): bool
    {
        if ($this->isAdminOf($tenant)) {
            return true;
        }

        return $this->hasPermission(
            $module instanceof PermissionModule ? $module->value : $module,
            $action instanceof PermissionAction ? $action->value : $action,
            $tenant->id,
        );
    }

    /**
     * The department IDs this user is restricted to within the tenant.
     * Null means unrestricted (tenant admin); an empty array means no access.
     *
     * @return array<int>|null
     */
    public function accessibleDepartmentIds(Tenant $tenant): ?array
    {
        if ($this->isAdminOf($tenant)) {
            return null;
        }

        return $this->departmentAccess()->wherePivot('tenant_id', $tenant->id)->pluck('departments.id')->all();
    }

    /**
     * The location IDs this user is restricted to within the tenant.
     * Null means unrestricted (tenant admin); an empty array means no access.
     *
     * @return array<int>|null
     */
    public function accessibleLocationIds(Tenant $tenant): ?array
    {
        if ($this->isAdminOf($tenant)) {
            return null;
        }

        return $this->locationAccess()->wherePivot('tenant_id', $tenant->id)->pluck('locations.id')->all();
    }
}
