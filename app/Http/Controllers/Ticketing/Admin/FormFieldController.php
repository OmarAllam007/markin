<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\Admin\StoreFormFieldRequest;
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
        abort_if($field->tenant_id !== $request->user()->current_tenant_id, 403);

        $field->delete();

        return redirect()->route('ticketing.admin.form-fields.show', $type)
            ->with('success', 'Custom field removed.');
    }
}
