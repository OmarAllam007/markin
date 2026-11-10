<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\Admin\StoreFormFieldRequest;
use App\Models\Tenant;
use App\Models\TicketTypeCustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormFieldController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAdmin($request, $tenantId, PermissionAction::Edit);

        $customCounts = TicketTypeCustomField::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('ticket_type, count(*) as count')
            ->groupBy('ticket_type')
            ->pluck('count', 'ticket_type');

        $types = collect(TicketType::cases())->map(fn ($type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'base_field_count' => count($type->baseFields()),
            'custom_field_count' => $customCounts->get($type->value, 0),
        ]);

        return Inertia::render('ticketing/admin/form-fields/Index', [
            'types' => $types,
        ]);
    }

    public function show(Request $request, string $type): Response
    {
        $ticketType = TicketType::from($type);
        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAdmin($request, $tenantId, PermissionAction::Edit);

        $customFields = TicketTypeCustomField::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_type', $type)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('ticketing/admin/form-fields/Show', [
            'ticketType' => ['value' => $ticketType->value, 'label' => $ticketType->label()],
            'baseFields' => $ticketType->baseFields(),
            'customFields' => $customFields,
        ]);
    }

    public function store(StoreFormFieldRequest $request, string $type): RedirectResponse
    {
        $ticketType = TicketType::from($type);
        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAdmin($request, $tenantId, PermissionAction::Edit);

        TicketTypeCustomField::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
            'ticket_type' => $ticketType->value,
        ]);

        return redirect()->route('ticketing.admin.form-fields.show', $type)
            ->with('success', 'Custom field added.');
    }

    public function destroy(Request $request, string $type, TicketTypeCustomField $field): RedirectResponse
    {
        $this->authorizeTicketAdmin($request, $field->tenant_id, PermissionAction::Delete);

        $field->delete();

        return redirect()->route('ticketing.admin.form-fields.show', $type)
            ->with('success', 'Custom field removed.');
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
