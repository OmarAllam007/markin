<?php

namespace App\Http\Controllers;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\LeaveType;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId($request, PermissionAction::View);

        $types = LeaveType::query()
            ->where('tenant_id', $tenantId)
            ->withCount('requests')
            ->orderBy('name')
            ->get(['id', 'name', 'annual_days', 'is_paid', 'is_active']);

        return Inertia::render('leave-types/Index', ['leaveTypes' => $types]);
    }

    public function create(Request $request): Response
    {
        $this->tenantId($request, PermissionAction::Create);

        return Inertia::render('leave-types/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request, PermissionAction::Create);

        LeaveType::create($this->validated($request, $tenantId) + ['tenant_id' => $tenantId]);

        return redirect()->route('leave-types.index')->with('success', 'Leave type added.');
    }

    public function edit(Request $request, LeaveType $leaveType): Response
    {
        $this->tenantId($request, PermissionAction::Edit, $leaveType->tenant_id);

        return Inertia::render('leave-types/Edit', ['leaveType' => $leaveType->only([
            'id', 'name', 'annual_days', 'is_paid', 'is_active',
        ])]);
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->tenantId($request, PermissionAction::Edit, $leaveType->tenant_id);

        $leaveType->update($this->validated($request, $leaveType->tenant_id, $leaveType->id));

        return redirect()->route('leave-types.index')->with('success', 'Leave type updated.');
    }

    public function destroy(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $this->tenantId($request, PermissionAction::Delete, $leaveType->tenant_id);

        // Requests keep pointing at their type, so a used type is deactivated, not deleted.
        if ($leaveType->requests()->exists()) {
            $leaveType->update(['is_active' => false]);

            return redirect()->route('leave-types.index')
                ->with('success', 'Leave type has requests, so it was deactivated instead of deleted.');
        }

        $leaveType->delete();

        return redirect()->route('leave-types.index')->with('success', 'Leave type deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('leave_types', 'name')->where('tenant_id', $tenantId)->ignore($ignoreId),
            ],
            'annual_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    private function tenantId(Request $request, PermissionAction $action, ?int $tenantId = null): int
    {
        $tenantId ??= $request->user()->current_tenant_id;
        abort_if(! $tenantId, 403, 'No active company selected.');

        $tenant = Tenant::findOrFail($tenantId);
        abort_if(! $request->user()->canAccessTenant($tenant), 403, 'You do not have access to this tenant.');
        abort_if(! $request->user()->canPerform($tenant, PermissionModule::Leaves, $action), 403);

        return $tenantId;
    }
}
