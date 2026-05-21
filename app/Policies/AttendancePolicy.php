<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\Tenant;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->current_tenant_id === null) {
            return false;
        }

        $tenant = Tenant::find($user->current_tenant_id);

        return $tenant !== null && $user->canAccessTenant($tenant);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $this->tenantAccessible($user, $attendance->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->adminOfCurrentTenant($user);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $this->tenantAccessible($user, $attendance->tenant_id)
            && $this->adminOfCurrentTenant($user);
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $this->tenantAccessible($user, $attendance->tenant_id)
            && $this->adminOfCurrentTenant($user);
    }

    public function restore(User $user, Attendance $attendance): bool
    {
        return $this->tenantAccessible($user, $attendance->tenant_id);
    }

    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return false;
    }

    /**
     * Approval is restricted to tenant admins so payroll corrections stay auditable.
     */
    public function approve(User $user, Attendance $attendance): bool
    {
        $tenant = Tenant::find($attendance->tenant_id);

        return $tenant !== null && $user->isAdminOf($tenant);
    }

    public function lock(User $user, Attendance $attendance): bool
    {
        $tenant = Tenant::find($attendance->tenant_id);

        return $tenant !== null && $user->isAdminOf($tenant);
    }

    private function tenantAccessible(User $user, int $tenantId): bool
    {
        $tenant = Tenant::find($tenantId);

        return $tenant !== null && $user->canAccessTenant($tenant);
    }

    private function adminOfCurrentTenant(User $user): bool
    {
        $tenant = Tenant::find($user->current_tenant_id);

        return $tenant !== null && $user->isAdminOf($tenant);
    }
}
