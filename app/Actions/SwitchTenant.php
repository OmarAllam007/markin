<?php

namespace App\Actions;

use App\Models\Tenant;
use App\Models\User;

class SwitchTenant
{
    public function execute(User $user, Tenant $tenant): void
    {
        if (! $user->canAccessTenant($tenant)) {
            abort(403, 'You do not have access to this company.');
        }

        $user->update(['current_tenant_id' => $tenant->id]);
    }
}
