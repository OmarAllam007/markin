<?php

namespace App\Http\Requests\Ticketing\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;
        $type = $this->route('type');

        return [
            'field_key' => [
                'required',
                'string',
                'alpha_dash',
                'max:100',
                Rule::unique('ticket_type_custom_fields')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('ticket_type', $type)),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['text', 'number', 'date', 'time', 'select', 'textarea', 'checkbox'])],
            'options' => ['required_if:type,select', 'nullable', 'array', 'min:1'],
            'options.*.value' => ['required_with:options', 'string', 'max:100'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:255'],
        ];
    }
}
