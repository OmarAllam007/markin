<?php

namespace App\Http\Controllers\Ticketing;

use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\StoreTicketRequest;
use App\Http\Requests\Ticketing\UpdateTicketRequest;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketGroup;
use App\Models\TicketPriority;
use App\Models\TicketSla;
use App\Models\TicketTypeCustomField;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'priority_id' => ['nullable', 'integer'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        $tickets = Ticket::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'requester:id,name',
                'category:id,name,color',
                'priority:id,name,color',
                'technician:id,name',
            ])
            ->when($request->search, fn ($q, $search) => $q->where('subject', 'like', "%{$search}%"))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->priority_id, fn ($q, $id) => $q->where('priority_id', $id))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $categories = TicketCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $priorities = TicketPriority::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color']);

        return Inertia::render('ticketing/tickets/Index', [
            'tickets' => $tickets,
            'categories' => $categories,
            'priorities' => $priorities,
            'statuses' => collect(TicketStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'filters' => $request->only(['search', 'status', 'category_id', 'priority_id']),
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        $customFieldsByType = TicketTypeCustomField::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn ($f) => $f->ticket_type->value);

        return Inertia::render('ticketing/tickets/Create', [
            'categories' => TicketCategory::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with('subcategories:id,category_id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'color', 'icon', 'ticket_type'])
                ->map(function ($cat) use ($customFieldsByType) {
                    $type = $cat->ticket_type;
                    $catArr = $cat->toArray();
                    $catArr['base_fields'] = $type ? $type->baseFields() : [];
                    $catArr['custom_fields'] = $type
                        ? ($customFieldsByType->get($type->value)?->map->only(['field_key', 'label', 'type', 'options', 'is_required', 'sort_order'])->values()->toArray() ?? [])
                        : [];

                    return $catArr;
                }),
            'priorities' => TicketPriority::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'color']),
            'slas' => TicketSla::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get(['id', 'name', 'first_response_hours', 'resolve_hours']),
            'groups' => TicketGroup::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get(['id', 'name']),
            'technicians' => User::query()
                ->whereHas('tenants', fn ($q) => $q->where('tenant_id', $tenantId))
                ->get(['id', 'name']),
            'employees' => Employee::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('english_name')
                ->get(['id', 'english_name', 'arabic_name']),
            'types' => collect(TicketType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $userId = $request->user()->id;

        $ticket = DB::transaction(function () use ($request, $tenantId, $userId): Ticket {
            $category = TicketCategory::find($request->validated('category_id'));

            $ticket = Ticket::create([
                ...$request->safe()->except('attachments'),
                'tenant_id' => $tenantId,
                'requester_id' => $userId,
                'creator_id' => $userId,
                'source' => $request->validated('source', 'web'),
                'type' => $category?->ticket_type ?? $request->validated('type'),
                'status' => TicketStatus::Submitted,
                'submitted_at' => now(),
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('ticketing/attachments', 'public');

                    Attachment::create([
                        'attachable_type' => Ticket::class,
                        'attachable_id' => $ticket->id,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'uploaded_by' => $userId,
                    ]);
                }
            }

            return $ticket;
        });

        return redirect()->route('ticketing.tickets.show', $ticket)
            ->with('success', 'Ticket submitted successfully.');
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        abort_if($ticket->tenant_id !== $request->user()->current_tenant_id, 403);

        $ticket->load([
            'requester:id,name',
            'creator:id,name',
            'employee:id,english_name,arabic_name',
            'technician:id,name',
            'group:id,name',
            'category:id,name,color,icon',
            'subcategory:id,name',
            'priority:id,name,color',
            'sla:id,name,first_response_hours,resolve_hours',
            'approvals.approver:id,name',
            'approvals.delegatedTo:id,name',
            'attachments.uploader:id,name',
            'linkedTickets:id,subject,status',
        ]);

        return Inertia::render('ticketing/tickets/Show', [
            'ticket' => $ticket,
            'statuses' => collect(TicketStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
        ]);
    }

    public function edit(Request $request, Ticket $ticket): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        abort_if($ticket->tenant_id !== $tenantId, 403);

        $ticket->load(['category:id,name', 'subcategory:id,name', 'priority:id,name', 'sla:id,name', 'group:id,name']);

        return Inertia::render('ticketing/tickets/Edit', [
            'ticket' => $ticket->only([
                'id', 'subject', 'description', 'category_id', 'subcategory_id',
                'type', 'status', 'priority_id', 'sla_id', 'group_id',
                'technician_id', 'employee_id', 'due_date', 'form_data',
            ]),
            'categories' => TicketCategory::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with('subcategories:id,category_id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'color', 'icon']),
            'priorities' => TicketPriority::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'color']),
            'slas' => TicketSla::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get(['id', 'name']),
            'groups' => TicketGroup::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get(['id', 'name']),
            'technicians' => User::query()
                ->whereHas('tenants', fn ($q) => $q->where('tenant_id', $tenantId))
                ->get(['id', 'name']),
            'statuses' => collect(TicketStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        abort_if($ticket->tenant_id !== $request->user()->current_tenant_id, 403);

        $ticket->update($request->validated());

        return redirect()->route('ticketing.tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully.');
    }

    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_if($ticket->tenant_id !== $request->user()->current_tenant_id, 403);

        $ticket->delete();

        return redirect()->route('ticketing.tickets.index')
            ->with('success', 'Ticket deleted successfully.');
    }
}
