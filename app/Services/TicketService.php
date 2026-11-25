<?php

namespace App\Services;

use App\Enums\TicketGroupType;
use App\Enums\TicketLogType;
use App\Enums\TicketStatus;
use App\Mail\TicketCreatedMail;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\RequestStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketGroup;
use App\Models\TicketItem;
use App\Models\TicketSla;
use App\Models\TicketSubcategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service-catalog rules for tickets: which form a request uses, who it is
 * assigned to, and which SLA applies. Every setting lives on the hierarchy
 * node (category → subcategory → item); the most specific non-null wins.
 */
class TicketService
{
    /** Keys that map to ticket columns rather than form_data. */
    public const STANDARD_KEYS = ['subject', 'description', 'priority_id', 'attachments'];

    public const FIELD_TYPES = ['text', 'number', 'date', 'time', 'select', 'textarea', 'checkbox', 'computed', 'priority', 'attachments'];

    public const FORMULAS = ['days_between', 'hours_between'];

    /** Form used when no node in the hierarchy has a designed form. */
    public static function defaultFields(): array
    {
        return [
            ['key' => 'subject', 'label' => 'Subject', 'translations' => ['ar' => 'الموضوع'], 'type' => 'text', 'required' => true, 'width' => 'full'],
            ['key' => 'description', 'label' => 'Description', 'translations' => ['ar' => 'الوصف'], 'type' => 'textarea', 'required' => false, 'width' => 'full'],
            ['key' => 'priority_id', 'label' => 'Priority', 'translations' => ['ar' => 'الأولوية'], 'type' => 'priority', 'required' => false, 'width' => 'half'],
            ['key' => 'attachments', 'label' => 'Attachments', 'translations' => ['ar' => 'المرفقات'], 'type' => 'attachments', 'required' => false, 'width' => 'full'],
        ];
    }

    /** @return array{group_id: ?int, technician_id: ?int, sla_id: ?int, form_fields: ?array} */
    public function resolveConfig(TicketCategory $category, ?TicketSubcategory $subcategory = null, ?TicketItem $item = null): array
    {
        $chain = collect([$item, $subcategory, $category])->filter();
        $pick = fn (string $attr) => $chain->map(fn ($node) => $node->{$attr})->first(fn ($v) => $v !== null);

        return [
            'group_id' => $pick('group_id'),
            'technician_id' => $pick('technician_id'),
            'sla_id' => $pick('sla_id'),
            'form_fields' => $pick('form_fields'),
            'approval_steps' => $pick('approval_steps'),
        ];
    }

    /**
     * Effective, ordered field list for a hierarchy node. Subject and the
     * category's ticket-type fields are always present and marked locked.
     */
    public function formFields(TicketCategory $category, ?TicketSubcategory $subcategory = null, ?TicketItem $item = null): array
    {
        $fields = $this->resolveConfig($category, $subcategory, $item)['form_fields'] ?? self::defaultFields();

        return $this->withLockedFields($fields, $category);
    }

    public function withLockedFields(array $fields, TicketCategory $category): array
    {
        $fields = collect($fields)->map(fn ($f) => [...$f, 'locked' => false])->keyBy('key');

        if (! $fields->has('subject')) {
            $fields = collect(['subject' => self::defaultFields()[0]])->merge($fields);
        }
        $fields['subject'] = [...$fields['subject'], 'required' => true, 'locked' => true];

        // Ticket-type fields (leave dates, trip destination…) can be moved or relabelled, never removed.
        foreach ($category->ticket_type?->baseFields() ?? [] as $base) {
            $designed = array_intersect_key($fields->get($base['key'], []), array_flip(['label', 'translations', 'width']));
            $fields[$base['key']] = [...$base, 'width' => $base['type'] === 'textarea' ? 'full' : 'half', ...$designed, 'locked' => true];
        }

        return $fields->values()->all();
    }

    /**
     * Swap each label for its translation in $locale, falling back to the
     * default `label`. `translations` stay so clients can switch language.
     */
    public static function localize(array $fields, string $locale): array
    {
        $pick = fn (array $x) => filled($x['translations'][$locale] ?? null) ? $x['translations'][$locale] : $x['label'];

        return array_map(fn (array $f) => [
            ...$f,
            'label' => $pick($f),
            'options' => isset($f['options']) ? array_map(fn ($o) => [...$o, 'label' => $pick($o)], $f['options']) : null,
        ], $fields);
    }

    /** Full rule set for a submission: the service hierarchy plus the resolved form. */
    public function submissionRules(array $input, int $tenantId): array
    {
        $rules = [
            'category_id' => ['required', 'integer', Rule::exists('ticket_categories', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'subcategory_id' => ['required', 'integer', Rule::exists('ticket_subcategories', 'id')->where('category_id', $input['category_id'] ?? null)->where('is_active', true)],
            'item_id' => ['nullable', 'integer', Rule::exists('ticket_items', 'id')->where('subcategory_id', $input['subcategory_id'] ?? null)->where('is_active', true)],
            'form_data' => ['nullable', 'array'],
        ];

        $category = TicketCategory::where('tenant_id', $tenantId)->find($input['category_id'] ?? null);

        if (! $category) {
            return $rules + ['subject' => ['required', 'string', 'max:255']];
        }

        $fields = $this->formFields(
            $category,
            TicketSubcategory::where('category_id', $category->id)->find($input['subcategory_id'] ?? null),
            TicketItem::where('tenant_id', $tenantId)->find($input['item_id'] ?? null),
        );

        return $rules + $this->rules($fields, $tenantId);
    }

    /** Validation rules for submitting a ticket against the given fields. */
    public function rules(array $fields, int $tenantId): array
    {
        $rules = [];

        foreach ($fields as $f) {
            $presence = ! empty($f['required']) ? 'required' : 'nullable';

            match ($f['type']) {
                'computed' => null,
                'priority' => $rules['priority_id'] = [$presence, 'integer', Rule::exists('ticket_priorities', 'id')->where('tenant_id', $tenantId)],
                'attachments' => $rules += [
                    'attachments' => [$presence, 'array'],
                    'attachments.*' => ['file', 'max:10240'],
                ],
                default => $rules[in_array($f['key'], self::STANDARD_KEYS, true) ? $f['key'] : "form_data.{$f['key']}"] = [
                    $presence,
                    ...$this->typeRules($f),
                ],
            };
        }

        $rules['subject'] = ['required', 'string', 'max:255'];

        // A date range must not end before it starts.
        foreach ($fields as $f) {
            if (($f['type'] ?? null) === 'computed' && count($f['args'] ?? []) === 2 && ($f['formula'] ?? null) === 'days_between') {
                [$from, $to] = $f['args'];
                $rules["form_data.{$to}"][] = "after_or_equal:form_data.{$from}";
            }
        }

        return $rules;
    }

    private function typeRules(array $f): array
    {
        return match ($f['type']) {
            'number' => ['numeric'],
            'date' => ['date'],
            'time' => ['date_format:H:i'],
            'checkbox' => ['boolean'],
            'select' => ['string', Rule::in(collect($f['options'] ?? [])->pluck('value')->all())],
            default => ['string', 'max:'.($f['type'] === 'textarea' ? 10000 : 255)],
        };
    }

    /** Fill computed fields in form_data. Server-side value is authoritative. */
    public function compute(array $fields, array $data): array
    {
        foreach ($fields as $f) {
            if (($f['type'] ?? null) !== 'computed') {
                continue;
            }

            [$a, $b] = array_pad($f['args'] ?? [], 2, null);
            $data[$f['key']] = self::evaluate($f['formula'] ?? '', $data[$a] ?? null, $data[$b] ?? null);
        }

        return $data;
    }

    public static function evaluate(string $formula, mixed $from, mixed $to): int|float|null
    {
        if (blank($from) || blank($to)) {
            return null;
        }

        try {
            $start = CarbonImmutable::parse($from);
            $end = CarbonImmutable::parse($to);
        } catch (Throwable) {
            return null;
        }

        return match ($formula) {
            // Inclusive: 1st → 3rd is 3 days.
            'days_between' => $end->lt($start) ? null : (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1,
            // Overnight ranges (22:00 → 02:00) wrap to the next day.
            'hours_between' => round(($end->lt($start) ? $end->addDay() : $end)->diffInMinutes($start, true) / 60, 2),
            default => null,
        };
    }

    /**
     * Create a ticket and apply the business rules: technician group,
     * technician, SLA due date, computed fields, then email both sides.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function create(array $data, User|Employee $requester, int $tenantId, array $files = []): Ticket
    {
        $category = TicketCategory::where('tenant_id', $tenantId)->findOrFail($data['category_id']);
        $subcategory = TicketSubcategory::where('tenant_id', $tenantId)->findOrFail($data['subcategory_id']);
        $item = isset($data['item_id']) ? TicketItem::where('tenant_id', $tenantId)->findOrFail($data['item_id']) : null;

        $config = $this->resolveConfig($category, $subcategory, $item);
        $fields = $this->formFields($category, $subcategory, $item);

        // Business rules decide the assignment first; the service catalog is the fallback.
        $userId = $requester instanceof User ? $requester->id : null;
        $data['source'] ??= $userId ? 'web' : 'mobile';
        $employee = $requester instanceof Employee ? $requester : Employee::where('tenant_id', $tenantId)->find($data['employee_id'] ?? null);
        $rule = app(BusinessRuleService::class)->match($tenantId, BusinessRuleService::context($data, $employee));

        $group = TicketGroup::query()
            ->where('tenant_id', $tenantId)
            ->where('type', TicketGroupType::Technician)
            ->where('is_active', true)
            ->find($rule?->group_id ?? $config['group_id']);

        if (! $group) {
            throw ValidationException::withMessages(['category_id' => 'No technician group is configured for this service. Ask an admin to set one in the service catalog.']);
        }

        $technicianId = $this->pickTechnician($group, $rule ? $rule->technician_id : $config['technician_id']);

        if (! $technicianId) {
            throw ValidationException::withMessages(['category_id' => "The technician group \"{$group->name}\" has no members."]);
        }

        $sla = $config['sla_id']
            ? TicketSla::where('tenant_id', $tenantId)->where('is_active', true)->find($config['sla_id'])
            : null;

        // Web users raise tickets as themselves; mobile requests come from the employee.

        $ticket = DB::transaction(function () use ($data, $requester, $userId, $tenantId, $files, $category, $fields, $group, $technicianId, $sla): Ticket {
            $ticket = Ticket::create([
                ...collect($data)->only(['subject', 'description', 'category_id', 'subcategory_id', 'item_id', 'priority_id', 'employee_id', 'source'])->all(),
                'tenant_id' => $tenantId,
                'requester_id' => $userId,
                'creator_id' => $userId,
                'employee_id' => $requester instanceof Employee ? $requester->id : ($data['employee_id'] ?? null),
                'source' => $data['source'] ?? ($userId ? 'web' : 'mobile'),
                'type' => $category->ticket_type,
                'status' => TicketStatus::Submitted,
                'request_status_id' => RequestStatus::initialFor($tenantId)?->id,
                'group_id' => $group->id,
                'technician_id' => $technicianId,
                'sla_id' => $sla?->id,
                // Counted in working hours when the SLA says so.
                'due_date' => $sla
                    ? ($sla->business_hours_only ? BusinessCalendar::forTenant($tenantId) : BusinessCalendar::always())->addMinutes(now(), $sla->resolve_hours * 60)
                    : null,
                'form_data' => $this->compute($fields, $data['form_data'] ?? []),
                'submitted_at' => now(),
            ]);

            foreach ($files as $file) {
                Attachment::create([
                    'attachable_type' => Ticket::class,
                    'attachable_id' => $ticket->id,
                    'file_path' => $file->store('ticketing/attachments', 'local'),
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => $userId,
                ]);
            }

            return $ticket;
        });

        if ($rule) {
            TicketLogger::log($ticket, TicketLogType::RuleApplied, meta: ['rule' => $rule->name, 'group' => $group->name]);
        }

        if (filled($requester->email)) {
            Mail::to($requester->email)->queue(new TicketCreatedMail($ticket, forTechnician: false));
        }
        Mail::to($ticket->technician)->queue(new TicketCreatedMail($ticket, forTechnician: true));

        app(TicketApprovalService::class)->applySteps($ticket, $config['approval_steps'] ?? []);

        return $ticket;
    }

    /** Configured technician if still in the group, else the member with the fewest open tickets. */
    private function pickTechnician(TicketGroup $group, ?int $preferredId): ?int
    {
        if ($preferredId && $group->members()->whereKey($preferredId)->exists()) {
            return $preferredId;
        }

        return $group->members()
            ->withCount(['assignedTickets as open_count' => fn ($q) => $q->whereIn('status', [TicketStatus::Submitted, TicketStatus::InReview])])
            ->orderBy('open_count')
            ->orderBy('users.id')
            ->value('users.id');
    }
}
