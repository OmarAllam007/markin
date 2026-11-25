<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\RequestStatusType;
use App\Http\Controllers\Controller;
use App\Models\RequestStatus;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The company's request statuses and the type behind each one. */
class RequestStatusController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->authorizeAdmin($request, PermissionAction::View);

        return Inertia::render('ticketing/admin/request-statuses/Index', [
            'statuses' => RequestStatus::forTenant($tenantId)->loadCount(['tickets' => fn ($q) => $q->withTrashed()]),
            'types' => collect(RequestStatusType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request);
        $this->save(new RequestStatus(['tenant_id' => $tenantId, 'sort_order' => RequestStatus::where('tenant_id', $tenantId)->max('sort_order') + 1]), $request, $tenantId);

        return back()->with('success', 'Status added.');
    }

    public function update(Request $request, RequestStatus $requestStatus): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request);
        abort_if($requestStatus->tenant_id !== $tenantId, 404);
        $this->save($requestStatus, $request, $tenantId);

        return back()->with('success', 'Status saved.');
    }

    public function destroy(Request $request, RequestStatus $requestStatus): RedirectResponse
    {
        abort_if($requestStatus->tenant_id !== $this->authorizeAdmin($request, PermissionAction::Delete), 404);

        if ($requestStatus->is_default) {
            return back()->with('error', 'Make another status the default before deleting this one.');
        }

        $requestStatus->delete();

        return back()->with('success', 'Status deleted.');
    }

    /** Moves a status up or down the list. */
    public function reorder(Request $request): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request);
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        DB::transaction(fn () => collect($ids)->each(
            fn ($id, $i) => RequestStatus::where('tenant_id', $tenantId)->whereKey($id)->update(['sort_order' => $i]),
        ));

        return back();
    }

    private function save(RequestStatus $status, Request $request, int $tenantId): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('request_statuses')->where('tenant_id', $tenantId)->ignore($status->id)],
            'name_translations' => ['nullable', 'array'],
            'name_translations.*' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::enum(RequestStatusType::class)],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_default' => ['boolean'],
        ]);

        // The default is where new requests start, so it has to be an open status.
        if (($data['is_default'] ?? false) && $data['type'] !== RequestStatusType::Open->value) {
            throw ValidationException::withMessages(['is_default' => 'Only an open status can be the default.']);
        }
        if ($status->is_default && ! ($data['is_default'] ?? false)) {
            throw ValidationException::withMessages(['is_default' => 'Pick another default instead of turning this one off.']);
        }

        DB::transaction(function () use ($status, $data, $tenantId) {
            if ($data['is_default'] ?? false) {
                RequestStatus::where('tenant_id', $tenantId)->update(['is_default' => false]);
            }
            $status->fill($data)->save();
        });
    }

    private function authorizeAdmin(Request $request, PermissionAction $action = PermissionAction::Edit): int
    {
        $tenantId = $request->user()->current_tenant_id;
        abort_if($tenantId === null, 403);
        abort_unless($request->user()->canPerform(Tenant::findOrFail($tenantId), PermissionModule::Ticketing, $action), 403, 'You do not have permission to perform this action.');

        return $tenantId;
    }
}
