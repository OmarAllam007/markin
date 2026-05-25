<?php

namespace App\Http\Requests\Ticketing\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sla_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'is_default' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
