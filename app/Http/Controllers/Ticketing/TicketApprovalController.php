<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\StoreTicketApprovalRequest;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Services\TicketApprovalService;
use App\Services\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketApprovalController extends Controller
{
    public function store(StoreTicketApprovalRequest $request, Ticket $ticket, TicketApprovalService $approvals): RedirectResponse
    {
        $approvals->request(
            $ticket,
            Employee::findOrFail($request->validated('approver_employee_id')),
            $request->stage(),
            $request->user(),
            $request->file('file'),
            $request->validated('description'),
        );

        return back()->with('success', 'Approval requested.');
    }

    public function destroy(Request $request, Ticket $ticket, TicketApproval $approval, TicketApprovalService $approvals): RedirectResponse
    {
        abort_if($approval->ticket_id !== $ticket->id, 404);
        // Same rule as requesting: the ticket's technician or a company admin.
        abort_unless(StoreTicketApprovalRequest::allows($request->user(), $ticket), 403);
        abort_unless($approval->action->value === 'pending', 422, 'Only pending approvals can be removed.');

        $approvals->remove($approval);

        return back()->with('success', 'Approval removed.');
    }

    /** Signed link from the approval email — no login. */
    public function show(TicketApproval $approval, TicketService $tickets): View
    {
        $ticket = $approval->ticket->load(['category', 'subcategory', 'item', 'employee:id,english_name', 'requester:id,name']);

        $fields = $ticket->category
            ? collect(TicketService::localize($tickets->formFields($ticket->category, $ticket->subcategory, $ticket->item), app()->getLocale()))
                ->reject(fn ($f) => in_array($f['key'], TicketService::STANDARD_KEYS, true))
            : collect();

        return view('ticket-approval', [
            'approval' => $approval->load('approverEmployee:id,english_name'),
            'ticket' => $ticket,
            'fields' => $fields,
            // Only the stage that was emailed can answer, and only once.
            'canRespond' => $approval->action->value === 'pending' && $approval->notified_at !== null && $ticket->status->value === 'in_review',
        ]);
    }

    public function respond(Request $request, TicketApproval $approval, TicketApprovalService $approvals): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'comments' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ]);

        abort_unless($approval->action->value === 'pending' && $approval->notified_at && $approval->ticket->status->value === 'in_review', 409, 'This approval is no longer open.');

        $approvals->respond($approval, $data['decision'] === 'approve', $data['comments'] ?? null);

        return redirect()->to($request->fullUrl())->with('success', 'Thank you — your response was recorded.');
    }
}
