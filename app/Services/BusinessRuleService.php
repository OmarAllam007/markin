<?php

namespace App\Services;

use App\Enums\TicketGroupType;
use App\Enums\TicketSource;
use App\Models\BusinessRule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\TicketCategory;
use App\Models\TicketItem;
use App\Models\TicketPriority;
use App\Models\TicketSubcategory;
use Illuminate\Validation\Rule;

/** Picks the assignment for a new request from the company's ordered business rules. */
class BusinessRuleService
{
    /** Condition fields and the table their values come from (null = fixed list). */
    public const FIELDS = [
        'department' => Department::class,
        'employee' => Employee::class,
        'location' => Location::class,
        'category' => TicketCategory::class,
        'subcategory' => TicketSubcategory::class,
        'item' => TicketItem::class,
        'priority' => TicketPriority::class,
        'source' => null,
    ];

    public const OPERATORS = ['in', 'not_in'];

    /**
     * What a request looks like to the rules.
     *
     * @return array<string, int|string|null>
     */
    public static function context(array $data, ?Employee $employee): array
    {
        return [
            'department' => $employee?->department_id,
            'employee' => $employee?->id,
            'location' => $employee?->location_id,
            'category' => isset($data['category_id']) ? (int) $data['category_id'] : null,
            'subcategory' => isset($data['subcategory_id']) ? (int) $data['subcategory_id'] : null,
            'item' => isset($data['item_id']) ? (int) $data['item_id'] : null,
            'priority' => isset($data['priority_id']) ? (int) $data['priority_id'] : null,
            'source' => $data['source'] ?? null,
        ];
    }

    /** First active rule, in order, whose conditions hold and whose group can still take work. */
    public function match(int $tenantId, array $context): ?BusinessRule
    {
        return BusinessRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('group', fn ($q) => $q->where('is_active', true)->where('type', TicketGroupType::Technician))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(fn (BusinessRule $rule) => self::matches($rule, $context));
    }

    public static function matches(BusinessRule $rule, array $context): bool
    {
        $results = collect($rule->conditions)->map(function (array $c) use ($context) {
            $value = $context[$c['field']] ?? null;
            // Values are compared as strings: ids arrive from JSON as ints or strings.
            $in = $value !== null && in_array((string) $value, array_map('strval', $c['values']), true);

            return $c['operator'] === 'in' ? $in : ! $in;
        });

        return $rule->match === 'any' ? $results->contains(true) : $results->every(fn ($r) => $r);
    }

    /** @return array<string, mixed> */
    public static function rules(int $tenantId, ?int $groupId): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['boolean'],
            'match' => ['required', Rule::in(['all', 'any'])],
            'conditions' => ['required', 'array', 'min:1', 'max:20'],
            'conditions.*.field' => ['required', Rule::in(array_keys(self::FIELDS))],
            'conditions.*.operator' => ['required', Rule::in(self::OPERATORS)],
            'conditions.*.values' => ['required', 'array', 'min:1'],
            'conditions.*.values.*' => ['required'],
            'group_id' => ['required', 'integer', Rule::exists('ticket_groups', 'id')->where('tenant_id', $tenantId)->where('type', TicketGroupType::Technician->value)],
            'technician_id' => ['nullable', 'integer', Rule::exists('ticket_group_user', 'user_id')->where('group_id', $groupId)],
        ];
    }

    /** Drops values that don't belong to this company, so a rule can't point at another tenant's data. */
    public static function cleanConditions(array $conditions, int $tenantId): array
    {
        return collect($conditions)->map(function (array $c) use ($tenantId) {
            $model = self::FIELDS[$c['field']];
            $values = $model === null
                ? array_values(array_intersect(array_map('strval', $c['values']), array_map(fn ($s) => $s->value, TicketSource::cases())))
                : $model::where('tenant_id', $tenantId)->whereIn('id', $c['values'])->pluck('id')->all();

            return ['field' => $c['field'], 'operator' => $c['operator'], 'values' => $values];
        })->filter(fn ($c) => $c['values'] !== [])->values()->all();
    }
}
