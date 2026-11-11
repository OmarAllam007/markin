<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStatus;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\LeaveRequest;
use App\Models\Tenant;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveService $leaveService) {}

    public function index(Request $request): Response
    {
        $request->validate(['status' => ['nullable', 'string', 'in:pending,approved,rejected']]);

        $tenantId = $this->authorizeLeaves($request, PermissionAction::View);
        $status = $request->string('status')->toString() ?: 'pending';

        $requests = LeaveRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('status', $status)
            ->with(['employee:id,arabic_name,english_name,employee_number', 'leaveType:id,name,is_paid', 'reviewer:id,name'])
            ->orderBy('start_date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('leaves/Index', [
            'requests' => $requests,
            'filters' => ['status' => $status],
            'counts' => [
                'pending' => LeaveRequest::where('tenant_id', $tenantId)->where('status', LeaveRequestStatus::Pending)->count(),
            ],
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeLeaves($request, PermissionAction::Edit, $leaveRequest->tenant_id);

        if ($leaveRequest->status === LeaveRequestStatus::Approved) {
            return back()->with('success', 'Request is already approved.');
        }

        $applied = $this->leaveService->approve($leaveRequest, $request->user());

        return back()->with('success', "Leave approved. {$applied} attendance day(s) marked as leave.");
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizeLeaves($request, PermissionAction::Edit, $leaveRequest->tenant_id);

        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:1000']]);

        $reverted = $this->leaveService->reject($leaveRequest, $request->user(), $data['review_note'] ?? null);

        $message = 'Leave rejected.';
        if ($reverted > 0) {
            $message .= " {$reverted} attendance day(s) reverted.";
        }

        return back()->with('success', $message);
    }

    private function authorizeLeaves(Request $request, PermissionAction $action, ?int $tenantId = null): int
    {
        $tenantId ??= $request->user()->current_tenant_id;
        abort_if(! $tenantId, 403, 'No active company selected.');

        $tenant = Tenant::findOrFail($tenantId);
        abort_if(! $request->user()->canAccessTenant($tenant), 403, 'You do not have access to this tenant.');
        abort_if(! $request->user()->canPerform($tenant, PermissionModule::Leaves, $action), 403);

        return $tenantId;
    }
}
