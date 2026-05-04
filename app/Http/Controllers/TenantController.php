<?php

namespace App\Http\Controllers;

use App\Actions\SwitchTenant;
use App\Enums\UserStatus;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\SwitchTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class TenantController extends Controller
{
    public function switch(SwitchTenantRequest $request, SwitchTenant $switchTenant): RedirectResponse
    {
        $tenant = Tenant::findOrFail($request->validated('tenant_id'));
        $switchTenant->execute($request->user(), $tenant);

        return redirect()->back();
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $parent = $request->user()->currentTenant;

        if (! $parent || ! $request->user()->canAccessTenant($parent)) {
            abort(403);
        }

        $tenant = Tenant::create([
            'name' => $request->validated('name'),
            'number_of_employees' => $request->validated('number_of_employees'),
            'parent_id' => $parent->id,
        ]);

        $tenant->users()->attach($request->user()->id, [
            'is_admin' => true,
            'is_supervisor' => false,
            'status' => UserStatus::Active->value,
        ]);

        return redirect()->back()->with('success', 'Sub-company created successfully.');
    }
}
