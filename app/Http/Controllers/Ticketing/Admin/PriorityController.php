<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\Admin\StorePriorityRequest;
use App\Http\Requests\Ticketing\Admin\UpdatePriorityRequest;
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

    public function create(): Response
    {
        return Inertia::render('ticketing/admin/priorities/Create');
    }

    public function store(StorePriorityRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

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
        abort_if($priority->tenant_id !== $request->user()->current_tenant_id, 403);

        return Inertia::render('ticketing/admin/priorities/Edit', [
            'priority' => $priority->only(['id', 'name', 'color', 'icon', 'sla_hours', 'is_default', 'sort_order']),
        ]);
    }

    public function update(UpdatePriorityRequest $request, TicketPriority $priority): RedirectResponse
    {
        abort_if($priority->tenant_id !== $request->user()->current_tenant_id, 403);

        $priority->update([
            ...$request->validated(),
            'is_default' => $request->boolean('is_default'),
        ]);

        return redirect()->route('ticketing.admin.priorities.index')
            ->with('success', 'Priority updated successfully.');
    }

    public function destroy(Request $request, TicketPriority $priority): RedirectResponse
    {
        abort_if($priority->tenant_id !== $request->user()->current_tenant_id, 403);

        $priority->delete();

        return redirect()->route('ticketing.admin.priorities.index')
            ->with('success', 'Priority deleted successfully.');
    }
}
