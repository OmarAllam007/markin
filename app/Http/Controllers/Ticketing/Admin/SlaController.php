<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\Admin\StoreSlaRequest;
use App\Http\Requests\Ticketing\Admin\UpdateSlaRequest;
use App\Models\Tenant;
use App\Models\TicketSla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SlaController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAdmin($request, $tenantId, PermissionAction::Edit);

        $slas = TicketSla::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ticketing/admin/slas/Index', [
            'slas' => $slas,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeTicketAdmin($request, $request->user()->current_tenant_id, PermissionAction::Edit);

        return Inertia::render('ticketing/admin/slas/Create');
    }

    public function store(StoreSlaRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAdmin($request, $tenantId, PermissionAction::Edit);

        TicketSla::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
            'business_hours_only' => $request->boolean('business_hours_only'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('ticketing.admin.slas.index')
            ->with('success', 'SLA policy created successfully.');
    }

    public function edit(Request $request, TicketSla $sla): Response
    {
        $this->authorizeTicketAdmin($request, $sla->tenant_id, PermissionAction::Edit);

        return Inertia::render('ticketing/admin/slas/Edit', [
            'sla' => $sla->only(['id', 'name', 'first_response_hours', 'resolve_hours', 'business_hours_only', 'is_active']),
        ]);
    }

    public function update(UpdateSlaRequest $request, TicketSla $sla): RedirectResponse
    {
        $this->authorizeTicketAdmin($request, $sla->tenant_id, PermissionAction::Edit);

        $sla->update([
            ...$request->validated(),
            'business_hours_only' => $request->boolean('business_hours_only'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('ticketing.admin.slas.index')
            ->with('success', 'SLA policy updated successfully.');
    }

    public function destroy(Request $request, TicketSla $sla): RedirectResponse
    {
        $this->authorizeTicketAdmin($request, $sla->tenant_id, PermissionAction::Delete);

        $sla->delete();

        return redirect()->route('ticketing.admin.slas.index')
            ->with('success', 'SLA policy deleted successfully.');
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
