<?php

namespace App\Http\Controllers;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Requests\StoreWorkShiftRequest;
use App\Http\Requests\UpdateWorkShiftRequest;
use App\Models\Tenant;
use App\Models\WorkShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkShiftController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('work-shifts/Index', [
                'workShifts' => [],
                'filters' => ['search' => null],
            ]);
        }

        $this->authorizesTenantAccess($request, $tenantId, PermissionModule::Shifts, PermissionAction::View);

        $workShifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->with('creator:id,name')
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('work-shifts/Index', [
            'workShifts' => $workShifts,
            'filters' => ['search' => $request->input('search')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('work-shifts/Create');
    }

    public function store(StoreWorkShiftRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $this->authorizesTenantAccess($request, $tenantId, PermissionModule::Shifts, PermissionAction::Create);

        $data = $request->validated();

        if (! ($data['overtime_enabled'] ?? false)) {
            $data['overtime_hours'] = null;
            $data['overtime_minutes'] = null;
            $data['calculate_overtime_early_checkin'] = false;
        }

        WorkShift::create([
            'tenant_id' => $tenantId,
            'created_by' => $request->user()->id,
            ...$data,
        ]);

        return redirect()->route('work-shifts.index')->with('success', 'Work shift created successfully.');
    }

    public function edit(WorkShift $workShift): Response
    {
        $this->authorizesTenantAccess(request(), $workShift->tenant_id, PermissionModule::Shifts, PermissionAction::Edit);

        return Inertia::render('work-shifts/Edit', [
            'workShift' => $workShift->only([
                'id', 'name', 'type', 'weekends',
                'checkin_time', 'checkout_time',
                'working_hours', 'working_minutes',
                'limit_checkin_from', 'limit_checkin_to',
                'overtime_enabled', 'overtime_hours', 'overtime_minutes',
                'calculate_overtime_early_checkin',
                'break_random_checks', 'break_hours', 'break_minutes',
                'break_start_from', 'break_start_to', 'break_apply_as_overtime',
            ]),
        ]);
    }

    public function update(UpdateWorkShiftRequest $request, WorkShift $workShift): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $workShift->tenant_id, PermissionModule::Shifts, PermissionAction::Edit);

        $data = $request->validated();

        if (! ($data['overtime_enabled'] ?? false)) {
            $data['overtime_hours'] = null;
            $data['overtime_minutes'] = null;
            $data['calculate_overtime_early_checkin'] = false;
        }

        $workShift->update($data);

        return redirect()->route('work-shifts.index')->with('success', 'Work shift updated successfully.');
    }

    public function destroy(Request $request, WorkShift $workShift): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $workShift->tenant_id, PermissionModule::Shifts, PermissionAction::Delete);

        $workShift->delete();

        return redirect()->route('work-shifts.index')->with('success', 'Work shift deleted successfully.');
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
