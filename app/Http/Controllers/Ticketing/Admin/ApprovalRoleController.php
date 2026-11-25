<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRole;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Company roles that approve requests (HR manager, finance manager…) and who holds each one. */
class ApprovalRoleController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $this->authorizeAdmin($request, PermissionAction::View);

        return Inertia::render('ticketing/admin/approval-roles/Index', [
            'roles' => ApprovalRole::where('tenant_id', $tenantId)->with('employee:id,english_name,email')->orderBy('name')->get(),
            'employees' => Employee::where('tenant_id', $tenantId)->orderBy('english_name')->get(['id', 'english_name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request);
        ApprovalRole::create([...$this->validated($request, $tenantId), 'tenant_id' => $tenantId]);

        return back()->with('success', 'Role added.');
    }

    public function update(Request $request, ApprovalRole $approvalRole): RedirectResponse
    {
        $tenantId = $this->authorizeAdmin($request);
        abort_if($approvalRole->tenant_id !== $tenantId, 404);
        $approvalRole->update($this->validated($request, $tenantId, $approvalRole));

        return back()->with('success', 'Role saved.');
    }

    public function destroy(Request $request, ApprovalRole $approvalRole): RedirectResponse
    {
        abort_if($approvalRole->tenant_id !== $this->authorizeAdmin($request, PermissionAction::Delete), 404);
        // Services still listing it skip the step and log why.
        $approvalRole->delete();

        return back()->with('success', 'Role deleted.');
    }

    private function validated(Request $request, int $tenantId, ?ApprovalRole $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('approval_roles')->where('tenant_id', $tenantId)->ignore($role)],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)->whereNotNull('email')->where('email', '!=', '')],
        ], ['employee_id.exists' => 'The person holding the role needs an email address to receive approvals.']);
    }

    private function authorizeAdmin(Request $request, PermissionAction $action = PermissionAction::Edit): int
    {
        $tenantId = $request->user()->current_tenant_id;
        abort_if($tenantId === null, 403);
        abort_unless($request->user()->canPerform(Tenant::findOrFail($tenantId), PermissionModule::Ticketing, $action), 403, 'You do not have permission to perform this action.');

        return $tenantId;
    }
}
