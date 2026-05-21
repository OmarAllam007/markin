<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyEmployeeOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'verification_token' => ['required', 'string', 'uuid'],
            'otp' => ['required', 'string', 'digits:4'],
            'device' => ['required', 'array'],
            'device.platform' => ['required', 'string', 'in:android,ios'],
            'device.android_id' => ['required', 'string', 'max:255'],
            'device.brand' => ['required', 'string', 'max:255'],
            'device.model' => ['required', 'string', 'max:255'],
            'device.sdk' => ['nullable', 'integer'],
            'device.app_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
