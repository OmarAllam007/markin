<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:notification,warning'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', 'string', 'in:locations_departments,employees'],
            'target_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'target_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'target_employee_ids' => ['nullable', 'array'],
            'target_employee_ids.*' => ['integer', 'exists:employees,id'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
