<?php

namespace App\Http\Controllers;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('locations/Index', [
                'locations' => [],
                'filters' => ['search' => null],
            ]);
        }

        $this->authorizesTenantAccess($request, $tenantId, PermissionModule::Locations, PermissionAction::View);

        $locations = Location::query()
            ->where('tenant_id', $tenantId)
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('locations/Index', [
            'locations' => $locations,
            'filters' => ['search' => $request->input('search')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('locations/Create');
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $this->authorizesTenantAccess($request, $tenantId, PermissionModule::Locations, PermissionAction::Create);

        Location::create([
            'tenant_id' => $tenantId,
            'name' => $request->validated()['name'],
            'coordinates' => $request->validated()['coordinates'],
        ]);

        return redirect()->route('locations.index')->with('success', 'Location created successfully.');
    }

    public function edit(Location $location): Response
    {
        $this->authorizesTenantAccess(request(), $location->tenant_id, PermissionModule::Locations, PermissionAction::Edit);

        return Inertia::render('locations/Edit', [
            'location' => $location->only(['id', 'name', 'coordinates']),
        ]);
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $location->tenant_id, PermissionModule::Locations, PermissionAction::Edit);

        $location->update($request->validated());

        return redirect()->route('locations.index')->with('success', 'Location updated successfully.');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $location->tenant_id, PermissionModule::Locations, PermissionAction::Delete);

        $location->delete();

        return redirect()->route('locations.index')->with('success', 'Location deleted successfully.');
    }

    private function authorizesTenantAccess(Request $request, int $tenantId, ?PermissionModule $module = null, ?PermissionAction $action = null): void
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (! $request->user()->canAccessTenant($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }

        if ($module !== null && ! $request->user()->canPerform($tenant, $module, $action)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }
}
