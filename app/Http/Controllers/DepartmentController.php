<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('departments/Index', [
                'departments' => [],
                'filters' => ['search' => null],
            ]);
        }

        $departments = Department::query()
            ->where('tenant_id', $tenantId)
            ->with('creator:id,name')
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('departments/Index', [
            'departments' => $departments,
            'filters' => ['search' => $request->input('search')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('departments/Create');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $this->authorizesTenantAccess($request, $tenantId);

        Department::create([
            'tenant_id' => $tenantId,
            'created_by' => $request->user()->id,
            'name' => $request->validated()['name'],
        ]);

        return redirect()->route('departments.index')->with('success', 'Department created successfully.');
    }

    public function edit(Department $department): Response
    {
        $this->authorizesTenantAccess(request(), $department->tenant_id);

        return Inertia::render('departments/Edit', [
            'department' => $department->only(['id', 'name']),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $department->tenant_id);

        $department->update($request->validated());

        return redirect()->route('departments.index')->with('success', 'Department updated successfully.');
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $department->tenant_id);

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Department deleted successfully.');
    }

    private function authorizesTenantAccess(Request $request, int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        if (! $request->user()->canAccessTenant($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }
    }
}
