<?php

namespace App\Http\Requests\Ticketing;

use App\Enums\TicketSource;
use App\Enums\TicketType;
use App\Models\TicketCategory;
use App\Models\TicketTypeCustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:ticket_subcategories,id'],
            'type' => ['nullable', new Enum(TicketType::class)],
            'source' => ['nullable', new Enum(TicketSource::class)],
            'priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'sla_id' => ['nullable', 'integer', 'exists:ticket_slas,id'],
            'group_id' => ['nullable', 'integer', 'exists:ticket_groups,id'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'form_data' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];

        $category = TicketCategory::find($this->input('category_id'));
        $type = $category?->ticket_type;

        if ($type) {
            foreach ($type->baseFields() as $field) {
                $fieldRule = $field['required'] ? 'required' : 'nullable';
                $typeRule = match ($field['type']) {
                    'date' => 'date',
                    'number' => 'numeric',
                    default => 'string',
                };
                $rules["form_data.{$field['key']}"] = [$fieldRule, $typeRule];
            }

            $tenantId = $this->user()->current_tenant_id;
            TicketTypeCustomField::query()
                ->where('tenant_id', $tenantId)
                ->where('ticket_type', $type->value)
                ->each(function (TicketTypeCustomField $field) use (&$rules): void {
                    $typeRule = match ($field->type) {
                        'date' => 'date',
                        'number' => 'numeric',
                        default => 'string',
                    };
                    $rules["form_data.{$field->field_key}"] = [
                        $field->is_required ? 'required' : 'nullable',
                        $typeRule,
                    ];
                });
        }

        return $rules;
    }
}
