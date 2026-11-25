<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Approves as "direct manager" for this department's employees.
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $this->user()->current_tenant_id)],
        ];
    }
}
