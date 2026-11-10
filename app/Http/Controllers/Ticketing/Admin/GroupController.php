<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\Admin\StoreGroupRequest;
use App\Http\Requests\Ticketing\Admin\UpdateGroupRequest;
use App\Models\Tenant;
use App\Models\TicketGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAdmin($request, $tenantId, PermissionAction::Edit);

        $groups = TicketGroup::query()
            ->where('tenant_id', $tenantId)
            ->withCount('tickets')
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ticketing/admin/groups/Index', [
            'groups' => $groups,
            'filters' => ['search' => $request->input('search')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeTicketAdmin($request, $request->user()->current_tenant_id, PermissionAction::Edit);

        return Inertia::render('ticketing/admin/groups/Create');
    }

    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAdmin($request, $tenantId, PermissionAction::Edit);

        TicketGroup::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('ticketing.admin.groups.index')
            ->with('success', 'Group created successfully.');
    }

    public function edit(Request $request, TicketGroup $group): Response
    {
        $this->authorizeTicketAdmin($request, $group->tenant_id, PermissionAction::Edit);

        return Inertia::render('ticketing/admin/groups/Edit', [
            'group' => $group->only(['id', 'name', 'description', 'is_active']),
        ]);
    }

    public function update(UpdateGroupRequest $request, TicketGroup $group): RedirectResponse
    {
        $this->authorizeTicketAdmin($request, $group->tenant_id, PermissionAction::Edit);

        $group->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('ticketing.admin.groups.index')
            ->with('success', 'Group updated successfully.');
    }

    public function destroy(Request $request, TicketGroup $group): RedirectResponse
    {
        $this->authorizeTicketAdmin($request, $group->tenant_id, PermissionAction::Delete);

        $group->delete();

        return redirect()->route('ticketing.admin.groups.index')
            ->with('success', 'Group deleted successfully.');
    }

    private function authorizeTicketAdmin(Request $request, ?int $tenantId, PermissionAction $action): void
    {
        abort_if($tenantId === null || $tenantId !== $request->user()->current_tenant_id, 403);

        $tenant = Tenant::findOrFail($tenantId);

        if (! $request->user()->canPerform($tenant, PermissionModule::Ticketing, $action)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }
}
