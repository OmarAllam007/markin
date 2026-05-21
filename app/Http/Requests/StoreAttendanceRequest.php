<?php

namespace App\Http\Requests;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'attendance_date' => [
                'required',
                'date',
                Rule::unique('attendances', 'attendance_date')->where(function ($query) {
                    return $query->where('employee_id', $this->integer('employee_id'))
                        ->whereNull('deleted_at');
                }),
            ],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'shift_id' => ['nullable', 'integer', 'exists:work_shifts,id'],
            'scheduled_check_in' => ['nullable', 'date_format:H:i'],
            'scheduled_check_out' => ['nullable', 'date_format:H:i'],
            'attendance_source' => ['required', Rule::enum(AttendanceSource::class)],
            'comments' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'gps_accuracy' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:500'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'is_holiday' => ['sometimes', 'boolean'],
            'edit_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
