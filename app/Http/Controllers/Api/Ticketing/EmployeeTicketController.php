<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEmployeeTicketRequest;
use App\Models\Employee;
use App\Models\Ticket;
use App\Services\TicketMessageService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $tickets = Ticket::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with(['category:id,name,name_translations,color', 'subcategory:id,name,name_translations', 'item:id,name,name_translations', 'technician:id,name', 'priority:id,name,color', 'requestStatus:id,name,name_translations,type,color'])
            ->latest()
            ->paginate(20);

        $tickets->through(fn (Ticket $t) => [
            'id' => $t->id,
            'subject' => $t->subject,
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'request_status' => $t->requestStatus?->only(['id', 'name', 'name_translations', 'type', 'color']),
            'category' => $t->category?->only(['id', 'name', 'name_translations', 'color']),
            'subcategory' => $t->subcategory?->only(['id', 'name', 'name_translations']),
            'item' => $t->item?->only(['id', 'name', 'name_translations']),
            'technician' => $t->technician?->name,
            'priority' => $t->priority?->only(['id', 'name', 'color']),
            'due_date' => $t->due_date?->toIso8601String(),
            'overdue' => $t->overdue,
            'created_at' => $t->created_at->toIso8601String(),
        ]);

        return ApiResponse::success('Tickets retrieved.', ['tickets' => $tickets]);
    }

    public function show(Request $request, Ticket $ticket, TicketService $tickets): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $isRequester = $ticket->employee_id === $employee->id;
        // Approvers open the ticket from their approval push; only the requester can reply.
        abort_unless($ticket->tenant_id === $employee->tenant_id
            && ($isRequester || $ticket->approvals()->where('approver_employee_id', $employee->id)->exists()), 404);

        // Full catalog rows: formFields() needs ticket_type and the form_fields config.
        $ticket->load([
            'category', 'subcategory', 'item', 'technician:id,name', 'group:id,name', 'priority:id,name,color',
            'attachments:id,attachable_type,attachable_id,file_name,file_size,mime_type',
            'approvals' => fn ($query) => $query->orderBy('stage_order')->orderBy('id'),
            'approvals.approver:id,name', 'approvals.approverEmployee:id,english_name,arabic_name',
            'requestStatus:id,name,name_translations,type,color',
            'messages' => fn ($query) => $query->orderBy('id'),
            'messages.user:id,name', 'messages.employee:id,english_name',
            'messages.requestStatus:id,name,name_translations,color',
            'messages.attachments:id,attachable_type,attachable_id,file_name,file_size,mime_type',
            'logs' => fn ($query) => $query->latest('id'),
            'logs.user:id,name', 'logs.employee:id,english_name',
        ]);

        // form_data labels, in the order the form was designed; the app picks the language.
        $fields = $ticket->category
            ? collect($tickets->formFields($ticket->category, $ticket->subcategory, $ticket->item))
                ->reject(fn ($f) => in_array($f['key'], TicketService::STANDARD_KEYS, true))
                ->values()
            : [];

        return ApiResponse::success('Ticket retrieved.', [
            'id' => $ticket->id,
            'is_requester' => $isRequester,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'request_status' => $ticket->requestStatus?->only(['id', 'name', 'name_translations', 'type', 'color']),
            'category' => $ticket->category?->only(['id', 'name', 'name_translations', 'color']),
            'subcategory' => $ticket->subcategory?->only(['id', 'name', 'name_translations']),
            'item' => $ticket->item?->only(['id', 'name', 'name_translations']),
            'technician' => $ticket->technician?->name,
            'group' => $ticket->group?->name,
            'priority' => $ticket->priority?->only(['id', 'name', 'color']),
            'due_date' => $ticket->due_date?->toIso8601String(),
            'overdue' => $ticket->overdue,
            'created_at' => $ticket->created_at->toIso8601String(),
            'fields' => $fields,
            'form_data' => (object) ($ticket->form_data ?? []),
            'attachments' => $ticket->attachments->map->only(['id', 'file_name', 'file_size', 'mime_type']),
            'approvals' => $ticket->approvals->map(fn ($approval) => [
                'id' => $approval->id,
                'stage_order' => $approval->stage_order,
                'action' => $approval->action->value,
                'description' => $approval->description,
                'comments' => $approval->comments,
                'approver' => $approval->approver?->name ?? $approval->approverEmployee?->english_name,
                'acted_at' => $approval->acted_at?->toIso8601String(),
            ]),
            'messages' => $ticket->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'author' => $m->authorName(),
                'from_requester' => $m->from_requester,
                'mine' => $m->employee_id === $employee->id,
                'request_status' => $m->requestStatus?->only(['id', 'name', 'name_translations', 'color']),
                'attachments' => $m->attachments->map->only(['id', 'file_name', 'file_size', 'mime_type']),
                'created_at' => $m->created_at->toIso8601String(),
            ]),
            'logs' => $ticket->logs->map(fn ($log) => [
                'id' => $log->id,
                'type' => $log->type->value,
                'actor' => $log->user?->name ?? $log->employee?->english_name,
                'changes' => $log->changes,
                'meta' => $log->meta,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
        ]);
    }

    /** What the reply sheet offers: statuses the requester may pick and colleagues to copy. */
    public function replyOptions(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        abort_unless($ticket->employee_id === $employee->id && $ticket->tenant_id === $employee->tenant_id, 404);

        return ApiResponse::success('Reply options retrieved.', [
            'statuses' => TicketMessageService::statusesFor($ticket->tenant_id, true)->map->only(['id', 'name', 'name_translations', 'type', 'color']),
            // Names only: the app doesn't need colleagues' addresses.
            'cc' => Employee::where('tenant_id', $ticket->tenant_id)
                ->whereNotNull('email')->where('email', '!=', '')
                ->whereKeyNot($employee->id)
                ->orderBy('english_name')
                ->get(['id', 'english_name', 'arabic_name']),
        ]);
    }

    public function storeMessage(Request $request, Ticket $ticket, TicketMessageService $messages): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        abort_unless($ticket->employee_id === $employee->id && $ticket->tenant_id === $employee->tenant_id, 404);

        $data = $request->validate(TicketMessageService::rules($ticket, true));
        $message = $messages->post($ticket, $employee, true, $data, $request->file('attachments', []));

        return ApiResponse::success('Message sent.', ['id' => $message->id], 201);
    }

    public function store(StoreEmployeeTicketRequest $request, TicketService $tickets): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $ticket = $tickets->create(
            [...$request->validated(), 'source' => 'mobile'],
            $employee,
            $employee->tenant_id,
            $request->file('attachments', []),
        );
        $ticket->load('technician:id,name');

        return ApiResponse::success('Your request has been submitted.', [
            'id' => $ticket->id,
            'status' => $ticket->status->value,
            'technician' => $ticket->technician?->name,
            'due_date' => $ticket->due_date?->toIso8601String(),
        ], 201);
    }
}
