<?php

namespace App\Http\Requests;

use App\Enums\ContractType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Employee details
            'arabic_name' => ['required', 'string', 'max:255'],
            'english_name' => ['required', 'string', 'max:255'],
            'mobile_country_code' => ['required', 'string', 'max:10'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'religion' => ['nullable', 'string', Rule::in(['muslim', 'christian', 'jewish', 'hindu', 'buddhist', 'other'])],

            // Job details
            'job_title_ar' => ['nullable', 'string', 'max:255'],
            'job_title_en' => ['nullable', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:100'],
            'social_security_number' => ['nullable', 'string', 'max:100'],
            'id_number' => ['nullable', 'string', 'max:100'],
            'working_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:working_start_date'],
            'contract_type' => ['nullable', Rule::enum(ContractType::class)],

            // Employee settings
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'check_biometrics' => ['boolean'],
            'send_reminders' => ['boolean'],
            'allow_remote_checkin' => ['boolean'],
            'allow_any_location_checkin' => ['boolean'],

            // Shift
            'work_shift_id' => ['nullable', 'integer', 'exists:work_shifts,id'],
        ];
    }
}
