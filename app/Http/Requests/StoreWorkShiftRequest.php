<?php

namespace App\Http\Requests;

use App\Enums\ShiftType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ShiftType::class)],
            'weekends' => ['present', 'array'],
            'weekends.*' => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'checkin_time' => ['nullable', 'required_if:type,fixed', 'date_format:H:i'],
            'checkout_time' => ['nullable', 'required_if:type,fixed', 'date_format:H:i', 'after:checkin_time'],
            'working_hours' => ['nullable', 'required_if:type,flexible', 'integer', 'min:0', 'max:23'],
            'working_minutes' => ['nullable', 'required_if:type,flexible', 'integer', 'min:0', 'max:59'],
            'limit_checkin_from' => ['nullable', 'date_format:H:i'],
            'limit_checkin_to' => ['nullable', 'date_format:H:i', 'after:limit_checkin_from'],
            'overtime_enabled' => ['boolean'],
            'overtime_hours' => ['nullable', 'required_if:overtime_enabled,true', 'integer', 'min:0', 'max:23'],
            'overtime_minutes' => ['nullable', 'required_if:overtime_enabled,true', 'integer', 'min:0', 'max:59'],
            'calculate_overtime_early_checkin' => ['boolean'],
            'break_random_checks' => ['nullable', 'integer', 'min:0'],
            'break_hours' => ['nullable', 'integer', 'min:0', 'max:23'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            'break_start_from' => ['nullable', 'date_format:H:i'],
            'break_start_to' => ['nullable', 'date_format:H:i', 'after:break_start_from'],
            'break_apply_as_overtime' => ['boolean'],
        ];
    }
}
