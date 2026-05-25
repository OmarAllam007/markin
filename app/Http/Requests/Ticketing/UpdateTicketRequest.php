<?php

namespace App\Http\Requests\Ticketing;

use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:ticket_subcategories,id'],
            'type' => ['nullable', new Enum(TicketType::class)],
            'source' => ['nullable', new Enum(TicketSource::class)],
            'status' => ['nullable', new Enum(TicketStatus::class)],
            'priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'sla_id' => ['nullable', 'integer', 'exists:ticket_slas,id'],
            'group_id' => ['nullable', 'integer', 'exists:ticket_groups,id'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'due_date' => ['nullable', 'date'],
            'form_data' => ['nullable', 'array'],
        ];
    }
}
