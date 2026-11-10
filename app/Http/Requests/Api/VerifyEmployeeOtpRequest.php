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
            'otp' => ['required', 'string', 'digits:6'],
            'device' => ['required', 'array'],
            'device.platform' => ['required', 'string', 'in:android,ios'],
            // device_id works on both platforms (iOS: Keychain UUID / identifierForVendor);
            // android_id is still accepted from older Android builds.
            'device.device_id' => ['required_without:device.android_id', 'string', 'max:255'],
            'device.android_id' => ['required_without:device.device_id', 'string', 'max:255'],
            'device.brand' => ['required', 'string', 'max:255'],
            'device.model' => ['required', 'string', 'max:255'],
            'device.sdk' => ['nullable', 'integer'],
            'device.app_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
