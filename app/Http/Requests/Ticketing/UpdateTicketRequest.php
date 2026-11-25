<?php

namespace App\Http\Requests\Ticketing;

use App\Enums\TicketGroupType;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Services\TicketAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TicketAccess::canEdit($this->user(), $this->route('ticket'));
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', Rule::exists('ticket_categories', 'id')->where('tenant_id', $tenantId)],
            'subcategory_id' => ['required', 'integer', Rule::exists('ticket_subcategories', 'id')->where('category_id', $this->input('category_id'))],
            'item_id' => ['nullable', 'integer', Rule::exists('ticket_items', 'id')->where('subcategory_id', $this->input('subcategory_id'))],
            'source' => ['nullable', new Enum(TicketSource::class)],
            'status' => ['nullable', new Enum(TicketStatus::class)],
            'priority_id' => ['nullable', 'integer', Rule::exists('ticket_priorities', 'id')->where('tenant_id', $tenantId)],
            'sla_id' => ['nullable', 'integer', Rule::exists('ticket_slas', 'id')->where('tenant_id', $tenantId)],
            'group_id' => ['required', 'integer', Rule::exists('ticket_groups', 'id')->where('tenant_id', $tenantId)->where('type', TicketGroupType::Technician->value)],
            'technician_id' => ['required', 'integer', Rule::exists('ticket_group_user', 'user_id')->where('group_id', $this->input('group_id'))],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'due_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return ['technician_id.exists' => 'The technician must be a member of the selected group.'];
    }
}
