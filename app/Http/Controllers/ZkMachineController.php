<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreZkMachineRequest;
use App\Http\Requests\UpdateZkMachineRequest;
use App\Models\Location;
use App\Models\ZkMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ZkMachineController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        $machines = ZkMachine::query()
            ->where('tenant_id', $tenantId)
            ->withCount(['rawLogs', 'pendingLogs'])
            ->with('location:id,name')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('zk-machines/Index', [
            'machines' => $machines,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        $locations = Location::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('zk-machines/Create', [
            'locations' => $locations,
        ]);
    }

    public function store(StoreZkMachineRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        ZkMachine::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('zk-machines.index')->with('success', 'Machine registered successfully.');
    }

    public function edit(Request $request, ZkMachine $zkMachine): Response
    {
        $this->authorizeTenantAccess($request, $zkMachine->tenant_id);

        $tenantId = $request->user()->current_tenant_id;

        $locations = Location::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('zk-machines/Edit', [
            'machine' => $zkMachine->only(['id', 'serial_number', 'name', 'location_id', 'secret_token', 'firmware_version', 'platform', 'last_sync_at', 'last_attlog_stamp']),
            'locations' => $locations,
            'pending_logs_count' => $zkMachine->pendingLogs()->count(),
        ]);
    }

    public function update(UpdateZkMachineRequest $request, ZkMachine $zkMachine): RedirectResponse
    {
        $this->authorizeTenantAccess($request, $zkMachine->tenant_id);

        $zkMachine->update($request->validated());

        return redirect()->route('zk-machines.index')->with('success', 'Machine updated successfully.');
    }

    public function destroy(Request $request, ZkMachine $zkMachine): RedirectResponse
    {
        $this->authorizeTenantAccess($request, $zkMachine->tenant_id);

        $zkMachine->delete();

        return redirect()->route('zk-machines.index')->with('success', 'Machine removed successfully.');
    }

    private function authorizeTenantAccess(Request $request, int $tenantId): void
    {
        if ($request->user()->current_tenant_id !== $tenantId) {
            abort(403);
        }
    }
}
