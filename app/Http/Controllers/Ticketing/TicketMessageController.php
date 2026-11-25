<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\StoreTicketApprovalRequest;
use App\Models\Ticket;
use App\Services\TicketMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketMessageController extends Controller
{
    public function store(Request $request, Ticket $ticket, TicketMessageService $messages): RedirectResponse
    {
        $user = $request->user();
        // The technician side (assigned technician or company admin) or the web user who raised it.
        $isStaff = StoreTicketApprovalRequest::allows($user, $ticket);
        $fromRequester = ! $isStaff && TicketMessageService::isRequester($user, $ticket);
        abort_unless($isStaff || ($fromRequester && $ticket->tenant_id === $user->current_tenant_id), 403);

        $data = $request->validate(TicketMessageService::rules($ticket, $fromRequester));
        $messages->post($ticket, $user, $fromRequester, $data, $request->file('attachments', []));

        return back()->with('success', 'Message sent.');
    }
}
