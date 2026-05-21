<?php

namespace App\Http\Requests;

use App\Enums\AttendanceVia;
use App\Enums\TemporaryShiftCalculation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'subdomain' => ['nullable', 'string', 'max:63', 'alpha_dash',
                Rule::unique('company_settings', 'subdomain')->where(
                    fn ($q) => $q->where('tenant_id', '!=', $this->user()->current_tenant_id)
                ),
            ],
            'cr_number' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_number' => ['nullable', 'string', 'max:20'],
            'terms' => ['nullable', 'string'],
            'policy' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'attendance_via' => ['required', Rule::enum(AttendanceVia::class)],
            'checkin_before_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'checkout_after_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'allow_temporary_shifts' => ['boolean'],
            'temporary_shift_calculation' => ['nullable', Rule::requiredIf($this->boolean('allow_temporary_shifts')), Rule::enum(TemporaryShiftCalculation::class)],
            'check_biometrics' => ['boolean'],
            'send_reminders' => ['boolean'],
            'allow_remote_checkin' => ['boolean'],
            'allow_any_location_checkin' => ['boolean'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }
}
