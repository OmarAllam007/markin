<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\TicketGroupType;
use App\Enums\TicketSource;
use App\Http\Controllers\Controller;
use App\Models\BusinessRule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TicketCategory;
use App\Models\TicketGroup;
use App\Models\TicketItem;
use App\Models\TicketPriority;
use App\Models\TicketSubcategory;
use App\Services\BusinessRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BusinessRuleController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->authorizeAdmin($request, PermissionAction::View);
        $byTenant = fn (string $model, array $columns, string $order = 'name') => $model::where('tenant_id', $tenantId)->orderBy($order)->get($columns);

        return Inertia::render('ticketing/admin/business-rules/Index', [
            'rules' => BusinessRule::where('tenant_id', $tenantId)
                ->with(['group:id,name', 'technician:id,name'])
                ->orderBy('sort_order')->orderBy('id')
                ->get(),
            // Everything a condition can point at, as { id, name } lists per field.
            'options' => [
                'department' => $byTenant(Department::class, ['id', 'name']),
                'employee' => Employee::where('tenant_id', $tenantId)->orderBy('english_name')->get(['id', 'english_name', 'employee_number'])
                    ->map(fn ($e) => ['id' => $e->id, 'name' => $e->employee_number ? "{$e->english_name} ({$e->employee_number})" : $e->english_name]),
                'location' => $byTenant(Location::class, ['id', 'name']),
                'category' => $byTenant(TicketCategory::class, ['id', 'name']),
                'subcategory' => TicketSubcategory::where('tenant_id', $tenantId)->with('category:id,name')->orderBy('name')->get(['id', 'name', 'category_id'])
                    ->map(fn ($s) => ['id' => $s->id, 'name' => "{$s->category?->name} › {$s->name}"]),
                'item' => TicketItem::where('tenant_id', $tenantId)->with('subcategory:id,name')->orderBy('name')->get(['id', 'name', 'subcategory_id'])
                    ->map(fn ($i) => ['id' => $i->id, 'name' => "{$i->subcategory?->name} › {$i->name}"]),
                'priority' => $byTenant(TicketPriority::class, ['id', 'name'], 'sort_order'),
                'source' => collect(TicketSource::cases())->map(fn ($s) => ['id' => $s->value, 'name' => ucfirst($s->value)]),
            ],
            'groups' => TicketGroup::where('tenant_id', $tenantId)->where('type', TicketGroupType::Technician)
                ->with('members:id,name')->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request);
        $this->save(new BusinessRule([
            'tenant_id' => $tenantId,
            // New rules go last, so existing behaviour doesn't change until they're moved up.
            'sort_order' => (int) BusinessRule::where('tenant_id', $tenantId)->max('sort_order') + 1,
        ]), $request, $tenantId);

        return back()->with('success', 'Rule added.');
    }

    public function update(Request $request, BusinessRule $businessRule): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request);
        abort_if($businessRule->tenant_id !== $tenantId, 404);

        // Switching a rule on or off from the list sends only that flag.
        if ($request->keys() === ['is_active']) {
            $businessRule->update($request->validate(['is_active' => ['required', 'boolean']]));

            return back();
        }

        $this->save($businessRule, $request, $tenantId);

        return back()->with('success', 'Rule saved.');
    }

    public function destroy(Request $request, BusinessRule $businessRule): RedirectResponse
    {
        abort_if($businessRule->tenant_id !== $this->authorizeAdmin($request, PermissionAction::Delete), 404);
        $businessRule->delete();

        return back()->with('success', 'Rule deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request);
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        DB::transaction(fn () => collect($ids)->each(
            fn ($id, $i) => BusinessRule::where('tenant_id', $tenantId)->whereKey($id)->update(['sort_order' => $i]),
        ));

        return back();
    }

    private function save(BusinessRule $rule, Request $request, int $tenantId): void
    {
        $data = $request->validate(
            BusinessRuleService::rules($tenantId, $request->integer('group_id') ?: null),
            ['technician_id.exists' => 'The technician must be a member of the chosen group.'],
        );

        $data['conditions'] = BusinessRuleService::cleanConditions($data['conditions'], $tenantId);
        if ($data['conditions'] === []) {
            throw ValidationException::withMessages(['conditions' => 'Add at least one condition with a value.']);
        }

        $rule->fill($data)->save();
    }

    private function authorizeAdmin(Request $request, PermissionAction $action = PermissionAction::Edit): int
    {
        $tenantId = $request->user()->current_tenant_id;
        abort_if($tenantId === null, 403);
        abort_unless($request->user()->canPerform(Tenant::findOrFail($tenantId), PermissionModule::Ticketing, $action), 403, 'You do not have permission to perform this action.');

        return $tenantId;
    }
}
