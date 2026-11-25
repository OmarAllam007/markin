<?php

namespace App\Http\Controllers\Ticketing;

use App\Enums\NotificationType;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\RequestStatusType;
use App\Enums\TicketGroupType;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticketing\StoreTicketRequest;
use App\Http\Requests\Ticketing\UpdateTicketRequest;
use App\Models\Employee;
use App\Models\RequestStatus;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketGroup;
use App\Models\TicketItem;
use App\Models\TicketPriority;
use App\Models\TicketSla;
use App\Models\TicketSubcategory;
use App\Services\TicketAccess;
use App\Services\TicketMessageService;
use App\Services\TicketPush;
use App\Services\TicketService;
use App\Services\TicketSlaClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    private const FILTERS = ['search', 'request_status_id', 'status', 'category_id', 'priority_id', 'group_id', 'technician_id', 'from', 'to', 'overdue'];

    public function index(Request $request): Response
    {
        $tenantId = $this->authorizeTicketList($request);
        $user = $request->user();
        $seesAll = TicketAccess::seesAll($user, $tenantId);

        $tickets = $this->filteredTickets($request, $tenantId)
            ->with([
                'requester:id,name',
                'employee:id,english_name',
                'category:id,name,color',
                'priority:id,name,color',
                'technician:id,name',
                'group:id,name',
                'requestStatus:id,name,color',
            ])
            ->paginate(15)
            ->withQueryString();

        // Technicians filter within their own groups.
        $groups = TicketGroup::query()
            ->where('tenant_id', $tenantId)
            ->where('type', TicketGroupType::Technician)
            ->when(! $seesAll, fn ($q) => $q->whereIn('id', TicketAccess::groupIds($user, $tenantId)))
            ->with('members:id,name')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ticketing/tickets/Index', [
            'tickets' => $tickets,
            'categories' => TicketCategory::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'priorities' => TicketPriority::where('tenant_id', $tenantId)->orderBy('sort_order')->get(['id', 'name', 'color']),
            'requestStatuses' => RequestStatus::forTenant($tenantId)->map->only(['id', 'name', 'color', 'type']),
            'groups' => $groups->map->only(['id', 'name']),
            'technicians' => $groups->flatMap->members->unique('id')->sortBy('name')->values()->map->only(['id', 'name']),
            'statuses' => collect(TicketStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'filters' => $request->only(self::FILTERS),
            'seesAll' => $seesAll,
            'canCreate' => $user->canPerform(Tenant::findOrFail($tenantId), PermissionModule::Ticketing, PermissionAction::Create),
        ]);
    }

    /** The current list, every page, as a spreadsheet. */
    public function export(Request $request): StreamedResponse
    {
        $tenantId = $this->authorizeTicketList($request);

        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->fromArray(['#', 'Subject', 'Status', 'Approval', 'Service', 'Priority', 'Requester', 'Group', 'Technician', 'Created', 'Due', 'Overdue', 'Resolved', 'First response', 'Resolution time (h)', 'Clock']);
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $row = 2;
        $this->filteredTickets($request, $tenantId)
            ->with(['requester:id,name', 'employee:id,english_name', 'category:id,name', 'subcategory:id,name', 'item:id,name', 'priority:id,name', 'technician:id,name', 'group:id,name', 'requestStatus:id,name'])
            ->chunk(500, function ($tickets) use ($sheet, &$row) {
                foreach ($tickets as $t) {
                    $sheet->fromArray([
                        $t->id,
                        $t->subject,
                        $t->requestStatus?->name,
                        $t->status->label(),
                        collect([$t->category?->name, $t->subcategory?->name, $t->item?->name])->filter()->join(' › '),
                        $t->priority?->name,
                        $t->requester?->name ?? $t->employee?->english_name,
                        $t->group?->name,
                        $t->technician?->name,
                        $t->created_at?->format('Y-m-d H:i'),
                        $t->due_date?->format('Y-m-d H:i'),
                        $t->overdue ? 'Yes' : 'No',
                        $t->resolve_date?->format('Y-m-d H:i'),
                        $t->first_response_date?->format('Y-m-d H:i'),
                        round(TicketSlaClock::elapsed($t) / 60, 2),
                        TicketSlaClock::state($t),
                    ], null, "A{$row}");
                    $row++;
                }
            });

        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($sheet->getParent()))->save('php://output'),
            'tickets-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** Role permission or technician group membership opens the list; the query narrows what each one sees. */
    private function authorizeTicketList(Request $request): int
    {
        $tenantId = $request->user()->current_tenant_id;
        abort_if($tenantId === null, 403);
        abort_unless(TicketAccess::seesAll($request->user(), $tenantId) || TicketAccess::isTechnician($request->user(), $tenantId), 403, 'You do not have permission to perform this action.');

        return $tenantId;
    }

    private function filteredTickets(Request $request, int $tenantId): Builder
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'request_status_id' => ['nullable', 'regex:/^(\d+|open|on_hold|awaiting_approval|resolved|closed|cancelled|active)$/'],
            'status' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'priority_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'technician_id' => ['nullable', 'regex:/^(\d+|me|none)$/'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'overdue' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();

        return TicketAccess::scope(Ticket::query()->where('tenant_id', $tenantId), $user, $tenantId)
            ->when($request->search, function ($q, $search) {
                $id = ltrim($search, '#');
                $q->where(fn ($q) => $q
                    ->where('subject', 'like', "%{$search}%")
                    ->when(ctype_digit($id), fn ($q) => $q->orWhere('id', (int) $id))
                    ->orWhereHas('employee', fn ($e) => $e->where('english_name', 'like', "%{$search}%")->orWhere('arabic_name', 'like', "%{$search}%")->orWhere('employee_number', $search))
                    ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%")));
            })
            // A status, a whole type (e.g. every on-hold status), or "active" = not resolved/closed/cancelled.
            ->when($request->request_status_id, fn ($q, $value) => match (true) {
                ctype_digit((string) $value) => $q->where('request_status_id', $value),
                $value === 'active' => $q->whereHas('requestStatus', fn ($s) => $s->whereIn('type', [RequestStatusType::Open, RequestStatusType::OnHold, RequestStatusType::AwaitingApproval])),
                default => $q->whereHas('requestStatus', fn ($s) => $s->where('type', $value)),
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->priority_id, fn ($q, $id) => $q->where('priority_id', $id))
            ->when($request->group_id, fn ($q, $id) => $q->where('group_id', $id))
            ->when($request->technician_id, fn ($q, $value) => match ($value) {
                'me' => $q->where('technician_id', $user->id),
                'none' => $q->whereNull('technician_id'),
                default => $q->where('technician_id', $value),
            })
            ->when($request->from, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->to, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($request->boolean('overdue'), fn ($q) => $q->where('overdue', true))
            ->orderByDesc('created_at');
    }

    public function create(Request $request, TicketService $tickets): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAccess($request, $tenantId, PermissionAction::Create);

        return Inertia::render('ticketing/tickets/Create', [
            'categories' => TicketCategory::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with([
                    'subcategories' => fn ($q) => $q->where('is_active', true)->select('id', 'category_id', 'name'),
                    'subcategories.items' => fn ($q) => $q->where('is_active', true)->select('id', 'subcategory_id', 'name'),
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'color', 'icon', 'ticket_type']),
            'priorities' => TicketPriority::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'color', 'is_default']),
            // Re-resolved on partial reload as the user walks the service hierarchy.
            'formFields' => function () use ($request, $tenantId, $tickets) {
                $category = TicketCategory::where('tenant_id', $tenantId)->find($request->integer('category_id'));

                return $category ? TicketService::localize($tickets->formFields(
                    $category,
                    TicketSubcategory::where('category_id', $category->id)->find($request->integer('subcategory_id')),
                    TicketItem::where('tenant_id', $tenantId)->find($request->integer('item_id')),
                ), app()->getLocale()) : [];
            },
        ]);
    }

    public function store(StoreTicketRequest $request, TicketService $tickets): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $this->authorizeTicketAccess($request, $tenantId, PermissionAction::Create);

        $ticket = $tickets->create($request->validated(), $request->user(), $tenantId, $request->file('attachments', []));

        return redirect()->route('ticketing.tickets.show', $ticket)
            ->with('success', 'Ticket submitted successfully.');
    }

    public function show(Request $request, Ticket $ticket, TicketService $tickets): Response
    {
        abort_unless(TicketAccess::canView($request->user(), $ticket), 403);

        $ticket->load([
            'requester:id,name',
            'creator:id,name',
            'employee:id,english_name,arabic_name',
            'technician:id,name',
            'group:id,name',
            // Full catalog rows: formFields() needs ticket_type and the form_fields config.
            'category',
            'subcategory',
            'item',
            'priority:id,name,color',
            'sla:id,name,first_response_hours,resolve_hours',
            'approvals' => fn ($q) => $q->orderBy('stage_order')->orderBy('id'),
            'approvals.approver:id,name',
            'approvals.approverEmployee:id,english_name,email',
            'approvals.requestedBy:id,name',
            'approvals.attachments:id,attachable_type,attachable_id,file_name,file_size',
            'attachments.uploader:id,name',
            'linkedTickets:id,subject,status',
            'requestStatus:id,name,type,color',
            'messages' => fn ($q) => $q->orderBy('id'),
            'messages.user:id,name',
            'messages.employee:id,english_name',
            'messages.requestStatus:id,name,color',
            'messages.attachments:id,attachable_type,attachable_id,file_name,file_size',
        ]);

        $user = $request->user();
        $isStaff = $ticket->technician_id === $user->id || $user->isAdminOf(Tenant::findOrFail($ticket->tenant_id));
        $isRequester = TicketMessageService::isRequester($user, $ticket);
        $ccNames = Employee::whereIn('id', $ticket->messages->pluck('cc')->flatten()->filter()->unique())->pluck('english_name', 'id');
        $ticket->messages->each(fn ($m) => $m->setAttribute('cc_names', collect($m->cc)->map(fn ($id) => $ccNames[$id] ?? null)->filter()->values()));

        $fields = $ticket->category
            ? TicketService::localize($tickets->formFields($ticket->category, $ticket->subcategory, $ticket->item), app()->getLocale())
            : [];

        return Inertia::render('ticketing/tickets/Show', [
            'ticket' => $ticket,
            // Labels for form_data, in the order the form was designed.
            'detailFields' => collect($fields)
                ->reject(fn ($f) => in_array($f['key'], TicketService::STANDARD_KEYS, true))
                ->map(fn ($f) => ['key' => $f['key'], 'label' => $f['label'], 'options' => $f['options'] ?? null])
                ->values(),
            'logs' => $ticket->logs()->with(['user:id,name', 'employee:id,english_name'])->latest('id')->get(),
            'canRequestApproval' => $isStaff,
            'canEdit' => TicketAccess::canEdit($user, $ticket),
            // Elapsed is exact at render time; the page keeps it ticking from clock_running_since.
            'clock' => [
                'state' => TicketSlaClock::state($ticket),
                'elapsed_minutes' => TicketSlaClock::elapsed($ticket),
                'ticking' => TicketSlaClock::ticking($ticket),
                'business_hours' => (bool) $ticket->sla?->business_hours_only,
                'running_since' => $ticket->clock_running_since?->toIso8601String(),
                'paused_at' => $ticket->paused_at?->toIso8601String(),
                'budget_minutes' => $ticket->sla ? $ticket->sla->resolve_hours * 60 : null,
                'response_budget_minutes' => $ticket->sla ? $ticket->sla->first_response_hours * 60 : null,
                'server_now' => now()->toIso8601String(),
            ],
            'canReply' => $isStaff || $isRequester,
            // Resolving is for the technician side.
            'replyStatuses' => TicketMessageService::statusesFor($ticket->tenant_id, $isRequester && ! $isStaff)->map->only(['id', 'name', 'type', 'color']),
            'approverOptions' => fn () => Employee::where('tenant_id', $ticket->tenant_id)
                ->whereNotNull('email')->where('email', '!=', '')
                ->orderBy('english_name')
                ->get(['id', 'english_name', 'email']),
            'statuses' => collect(TicketStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
        ]);
    }

    public function edit(Request $request, Ticket $ticket): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        abort_unless(TicketAccess::canEdit($request->user(), $ticket), 403);

        $ticket->load(['category:id,name', 'subcategory:id,name', 'priority:id,name', 'sla:id,name', 'group:id,name']);

        return Inertia::render('ticketing/tickets/Edit', [
            'ticket' => $ticket->only([
                'id', 'subject', 'description', 'category_id', 'subcategory_id',
                'item_id', 'type', 'status', 'priority_id', 'sla_id', 'group_id',
                'technician_id', 'employee_id', 'due_date', 'form_data',
            ]),
            'categories' => TicketCategory::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with('subcategories:id,category_id,name', 'subcategories.items:id,subcategory_id,name')
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
                ->where('type', TicketGroupType::Technician)
                ->where('is_active', true)
                ->with('members:id,name')
                ->get(['id', 'name']),
            'statuses' => collect(TicketStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(TicketAccess::canEdit($request->user(), $ticket), 403);

        $ticket->update($request->validated());

        if ($ticket->wasChanged('request_status_id') && $status = $ticket->requestStatus()->value('name')) {
            TicketPush::requester($ticket, NotificationType::TicketStatusChanged, "Your request is now {$status}.");
        }

        return redirect()->route('ticketing.tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully.');
    }

    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeTicketAccess($request, $ticket->tenant_id, PermissionAction::Delete);

        $ticket->delete();

        return redirect()->route('ticketing.tickets.index')
            ->with('success', 'Ticket deleted successfully.');
    }

    /**
     * Checks both that the ticket/tenant scope matches the caller's current
     * company, and that they hold the given Ticketing permission for it.
     */
    private function authorizeTicketAccess(Request $request, ?int $tenantId, PermissionAction $action): void
    {
        abort_if($tenantId === null || $tenantId !== $request->user()->current_tenant_id, 403);

        $tenant = Tenant::findOrFail($tenantId);

        if (! $request->user()->canPerform($tenant, PermissionModule::Ticketing, $action)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }
}
