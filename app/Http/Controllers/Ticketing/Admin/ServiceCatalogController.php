<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\TicketGroupType;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRole;
use App\Models\Tenant;
use App\Models\TicketCategory;
use App\Models\TicketGroup;
use App\Models\TicketItem;
use App\Models\TicketPriority;
use App\Models\TicketSla;
use App\Models\TicketSubcategory;
use App\Services\TicketApprovalService;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One screen per tenant to configure the service hierarchy:
 * category → subcategory → item, each with its business rule
 * (technician group, technician, SLA) and its request form.
 */
class ServiceCatalogController extends Controller
{
    private const LEVELS = [
        'category' => TicketCategory::class,
        'subcategory' => TicketSubcategory::class,
        'item' => TicketItem::class,
    ];

    public function index(Request $request, TicketService $tickets): Response
    {
        $tenantId = $this->authorizeAdmin($request);

        $request->validate(['node' => ['nullable', 'regex:/^(category|subcategory|item):\d+$/']]);

        $config = ['id', 'name', 'group_id', 'technician_id', 'sla_id', 'form_fields', 'approval_steps'];
        $categories = TicketCategory::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'subcategories' => fn ($q) => $q->select([...$config, 'category_id', 'is_active']),
                'subcategories.items' => fn ($q) => $q->select([...$config, 'subcategory_id', 'is_active']),
            ])
            ->orderBy('name')
            ->get([...$config, 'ticket_type', 'is_active']);

        $selected = null;
        if ($request->node) {
            [$level, $id] = explode(':', $request->node);
            $node = $this->findNode($level, (int) $id, $tenantId);
            [$category, $subcategory, $item] = $this->chain($node);
            $parentConfig = match ($level) {
                'category' => ['group_id' => null, 'technician_id' => null, 'sla_id' => null, 'form_fields' => null, 'approval_steps' => null],
                'subcategory' => $tickets->resolveConfig($category),
                'item' => $tickets->resolveConfig($category, $subcategory),
            };

            $selected = [
                'level' => $level,
                'id' => $node->id,
                'name' => $node->name,
                'path' => collect([$category->name, $subcategory?->name, $item?->name])->filter()->values(),
                'ticket_type' => $category->ticket_type?->label(),
                'group_id' => $node->group_id,
                'technician_id' => $node->technician_id,
                'sla_id' => $node->sla_id,
                'has_own_form' => $node->form_fields !== null,
                'approval_steps' => $node->approval_steps,
                'inherited' => collect($parentConfig)->except('form_fields'),
                // Designer starts from what the employee would see today.
                'fields' => $tickets->formFields($category, $subcategory, $item),
                'inherited_fields' => $tickets->withLockedFields($parentConfig['form_fields'] ?? TicketService::defaultFields(), $category),
            ];
        }

        return Inertia::render('ticketing/admin/service-catalog/Index', [
            'categories' => $categories,
            'selected' => $selected,
            'groups' => TicketGroup::query()
                ->where('tenant_id', $tenantId)
                ->where('type', TicketGroupType::Technician)
                ->where('is_active', true)
                ->with('members:id,name')
                ->orderBy('name')
                ->get(['id', 'name']),
            'slas' => TicketSla::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get(['id', 'name', 'first_response_hours', 'resolve_hours']),
            'priorities' => TicketPriority::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'color', 'is_default']),
            'approvalRoles' => ApprovalRole::where('tenant_id', $tenantId)->with('employee:id,english_name')->orderBy('name')->get(['id', 'name', 'employee_id']),
        ]);
    }

    public function update(Request $request, string $level, int $id): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request);
        $node = $this->findNode($level, $id, $tenantId);

        $data = $request->validate([
            'group_id' => ['nullable', 'integer', Rule::exists('ticket_groups', 'id')->where('tenant_id', $tenantId)->where('type', TicketGroupType::Technician->value)],
            'technician_id' => ['nullable', 'integer', Rule::exists('ticket_group_user', 'user_id')->where('group_id', $request->input('group_id'))],
            'sla_id' => ['nullable', 'integer', Rule::exists('ticket_slas', 'id')->where('tenant_id', $tenantId)],
            // null = inherit; [] = no approvals on this level.
            'approval_steps' => ['nullable', 'array'],
            'approval_steps.*' => ['string', 'distinct', Rule::in([
                TicketApprovalService::DIRECT_MANAGER,
                TicketApprovalService::DEPARTMENT_MANAGER,
                ...ApprovalRole::where('tenant_id', $tenantId)->pluck('id')->map(fn ($id) => "role:{$id}"),
            ])],
            'form_fields' => ['nullable', 'array'],
            'form_fields.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', 'max:60', 'distinct'],
            'form_fields.*.label' => ['required', 'string', 'max:255'],
            'form_fields.*.translations' => ['nullable', 'array'],
            'form_fields.*.translations.*' => ['nullable', 'string', 'max:255'],
            'form_fields.*.type' => ['required', Rule::in(TicketService::FIELD_TYPES)],
            'form_fields.*.required' => ['boolean'],
            'form_fields.*.width' => ['required', Rule::in(['half', 'full'])],
            'form_fields.*.options' => ['nullable', 'required_if:form_fields.*.type,select', 'array'],
            'form_fields.*.options.*.value' => ['required', 'string', 'max:100'],
            'form_fields.*.options.*.label' => ['required', 'string', 'max:255'],
            'form_fields.*.options.*.translations' => ['nullable', 'array'],
            'form_fields.*.options.*.translations.*' => ['nullable', 'string', 'max:255'],
            'form_fields.*.formula' => ['nullable', 'required_if:form_fields.*.type,computed', Rule::in(TicketService::FORMULAS)],
            'form_fields.*.args' => ['nullable', 'required_if:form_fields.*.type,computed', 'array', 'size:2'],
            'form_fields.*.args.*' => ['string'],
        ], [
            'technician_id.exists' => 'The technician must be a member of the group chosen on this level.',
        ]);

        if (filled($data['technician_id'] ?? null) && blank($data['group_id'] ?? null)) {
            throw ValidationException::withMessages(['technician_id' => 'Pick the technician group on this level too.']);
        }

        if (isset($data['form_fields'])) {
            $data['form_fields'] = collect($data['form_fields'])
                ->map(fn ($f) => collect($f)->only(['key', 'label', 'translations', 'type', 'required', 'width', 'options', 'formula', 'args'])->filter(fn ($v) => $v !== null)->all())
                ->values()
                ->all();
        }

        $node->update([
            'group_id' => $data['group_id'] ?? null,
            'technician_id' => $data['technician_id'] ?? null,
            'sla_id' => $data['sla_id'] ?? null,
            'form_fields' => $data['form_fields'] ?? null,
            'approval_steps' => isset($data['approval_steps']) ? array_values($data['approval_steps']) : null,
        ]);

        return back()->with('success', 'Service settings saved.');
    }

    public function storeItem(Request $request, TicketSubcategory $subcategory): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request);
        abort_if($subcategory->tenant_id !== $tenantId, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_translations' => ['nullable', 'array'],
            'name_translations.*' => ['nullable', 'string', 'max:255'],
        ]);

        $item = $subcategory->items()->create([...$data, 'tenant_id' => $tenantId]);

        return redirect()->route('ticketing.admin.service-catalog.index', ['node' => "item:{$item->id}"])
            ->with('success', 'Item added.');
    }

    public function destroyItem(Request $request, TicketItem $item): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request, PermissionAction::Delete);
        abort_if($item->tenant_id !== $tenantId, 404);

        $item->delete();

        return redirect()->route('ticketing.admin.service-catalog.index', ['node' => "subcategory:{$item->subcategory_id}"])
            ->with('success', 'Item removed.');
    }

    private function findNode(string $level, int $id, int $tenantId): Model
    {
        abort_unless(isset(self::LEVELS[$level]), 404);

        return self::LEVELS[$level]::where('tenant_id', $tenantId)->findOrFail($id);
    }

    /** @return array{0: TicketCategory, 1: ?TicketSubcategory, 2: ?TicketItem} */
    private function chain(Model $node): array
    {
        return match (true) {
            $node instanceof TicketItem => [$node->subcategory->category, $node->subcategory, $node],
            $node instanceof TicketSubcategory => [$node->category, $node, null],
            default => [$node, null, null],
        };
    }

    private function authorizeAdmin(Request $request, PermissionAction $action = PermissionAction::Edit): int
    {
        $tenantId = $request->user()->current_tenant_id;
        abort_if($tenantId === null, 403);

        if (! $request->user()->canPerform(Tenant::findOrFail($tenantId), PermissionModule::Ticketing, $action)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $tenantId;
    }
}
