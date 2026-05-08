<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Models\CompanySetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class TenantSettingsController extends Controller
{
    public function update(UpdateTenantSettingsRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $tenant = Tenant::findOrFail($tenantId);

        if (! $request->user()->canAccessTenant($tenant)) {
            abort(403);
        }

        $data = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            $existing = CompanySetting::where('tenant_id', $tenantId)->value('logo_path');
            if ($existing) {
                Storage::disk('public')->delete($existing);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        CompanySetting::updateOrCreate(['tenant_id' => $tenantId], $data);

        return redirect()->back()->with('success', 'Settings saved successfully.');
    }
}
