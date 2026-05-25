<?php

namespace App\Http\Controllers\Ticketing;

use App\Enums\TicketApprovalAction;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\StoreTicketApprovalRequest;
use App\Models\Ticket;
use App\Models\TicketApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TicketApprovalController extends Controller
{
    public function store(StoreTicketApprovalRequest $request, Ticket $ticket): RedirectResponse
    {
        $action = TicketApprovalAction::from($request->validated('action'));

        DB::transaction(function () use ($request, $ticket, $action): void {
            $nextStage = $ticket->approvals()->where('action', TicketApprovalAction::Pending)->min('stage_order') ?? 1;

            TicketApproval::create([
                'ticket_id' => $ticket->id,
                'stage_order' => $nextStage,
                'approver_user_id' => $request->user()->id,
                'action' => $action,
                'comments' => $request->validated('comments'),
                'delegated_to_user_id' => $request->validated('delegated_to_user_id'),
                'acted_at' => now(),
            ]);

            $ticket->update([
                'status' => match ($action) {
                    TicketApprovalAction::Approved => TicketStatus::Approved,
                    TicketApprovalAction::Rejected => TicketStatus::Rejected,
                    TicketApprovalAction::Returned => TicketStatus::InReview,
                    default => $ticket->status,
                },
                'resolve_date' => $action === TicketApprovalAction::Approved ? now() : null,
            ]);
        });

        return redirect()->route('ticketing.tickets.show', $ticket)
            ->with('success', 'Approval action recorded.');
    }
}
