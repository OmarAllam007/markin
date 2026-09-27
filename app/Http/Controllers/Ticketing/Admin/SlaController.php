<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\Admin\StoreSlaRequest;
use App\Http\Requests\Ticketing\Admin\UpdateSlaRequest;
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

        $slas = TicketSla::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ticketing/admin/slas/Index', [
            'slas' => $slas,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ticketing/admin/slas/Create');
    }

    public function store(StoreSlaRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

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
        abort_if($sla->tenant_id !== $request->user()->current_tenant_id, 403);

        return Inertia::render('ticketing/admin/slas/Edit', [
            'sla' => $sla->only(['id', 'name', 'first_response_hours', 'resolve_hours', 'business_hours_only', 'is_active']),
        ]);
    }

    public function update(UpdateSlaRequest $request, TicketSla $sla): RedirectResponse
    {
        abort_if($sla->tenant_id !== $request->user()->current_tenant_id, 403);

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
        abort_if($sla->tenant_id !== $request->user()->current_tenant_id, 403);

        $sla->delete();

        return redirect()->route('ticketing.admin.slas.index')
            ->with('success', 'SLA policy deleted successfully.');
    }
}
