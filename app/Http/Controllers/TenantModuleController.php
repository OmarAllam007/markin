<?php

namespace App\Http\Controllers;

use App\Enums\AppModule;
use App\Models\Tenant;
use App\Models\TenantModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantModuleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', 'in:'.implode(',', array_column(AppModule::cases(), 'value'))],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $tenant = Tenant::findOrFail($tenantId);

        if (! $request->user()->isAdminOf($tenant)) {
            abort(403);
        }

        TenantModule::updateOrCreate(
            ['tenant_id' => $tenantId, 'module' => $validated['module']],
            ['is_enabled' => $validated['is_enabled']]
        );

        $label = AppModule::from($validated['module'])->label();
        $status = $validated['is_enabled'] ? 'enabled' : 'disabled';

        return redirect()->back()->with('success', "{$label} module {$status} successfully.");
    }
}
