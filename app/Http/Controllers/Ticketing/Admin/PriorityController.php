<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\Admin\StorePriorityRequest;
use App\Http\Requests\Ticketing\Admin\UpdatePriorityRequest;
use App\Models\Tenant;
use App\Models\TicketPriority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriorityController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAdmin($request, $tenantId, PermissionAction::Edit);

        $priorities = TicketPriority::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ticketing/admin/priorities/Index', [
            'priorities' => $priorities,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeTicketAdmin($request, $request->user()->current_tenant_id, PermissionAction::Edit);

        return Inertia::render('ticketing/admin/priorities/Create');
    }

    public function store(StorePriorityRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAdmin($request, $tenantId, PermissionAction::Edit);

        TicketPriority::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
            'is_default' => $request->boolean('is_default'),
        ]);

        return redirect()->route('ticketing.admin.priorities.index')
            ->with('success', 'Priority created successfully.');
    }

    public function edit(Request $request, TicketPriority $priority): Response
    {
        $this->authorizeTicketAdmin($request, $priority->tenant_id, PermissionAction::Edit);

        return Inertia::render('ticketing/admin/priorities/Edit', [
            'priority' => $priority->only(['id', 'name', 'color', 'icon', 'sla_hours', 'is_default', 'sort_order']),
        ]);
    }

    public function update(UpdatePriorityRequest $request, TicketPriority $priority): RedirectResponse
    {
        $this->authorizeTicketAdmin($request, $priority->tenant_id, PermissionAction::Edit);

        $priority->update([
            ...$request->validated(),
            'is_default' => $request->boolean('is_default'),
        ]);

        return redirect()->route('ticketing.admin.priorities.index')
            ->with('success', 'Priority updated successfully.');
    }

    public function destroy(Request $request, TicketPriority $priority): RedirectResponse
    {
        $this->authorizeTicketAdmin($request, $priority->tenant_id, PermissionAction::Delete);

        $priority->delete();

        return redirect()->route('ticketing.admin.priorities.index')
            ->with('success', 'Priority deleted successfully.');
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
