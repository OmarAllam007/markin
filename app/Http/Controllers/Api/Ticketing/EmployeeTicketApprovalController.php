<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Enums\TicketApprovalAction;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Services\TicketApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeTicketApprovalController extends Controller
{
    public function respond(Request $request, Ticket $ticket, TicketApproval $approval, TicketApprovalService $approvals): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        abort_unless($ticket->tenant_id === $employee->tenant_id
            && $approval->ticket_id === $ticket->id
            && $approval->approver_employee_id === $employee->id, 404);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'comments' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ]);

        DB::transaction(function () use ($approval, $approvals, $data): void {
            $current = TicketApproval::query()->lockForUpdate()->findOrFail($approval->id);
            abort_unless($current->action === TicketApprovalAction::Pending
                && $current->notified_at !== null
                && $current->ticket->status->value === 'in_review', 409, 'This approval is no longer open.');

            $approvals->respond($current, $data['decision'] === 'approve', $data['comments'] ?? null);
        });

        return ApiResponse::success('Your decision was recorded.');
    }
}
