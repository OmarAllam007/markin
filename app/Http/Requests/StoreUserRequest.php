<?php

namespace App\Http\Requests;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_admin' => $this->boolean('is_admin'),
            'is_supervisor' => $this->boolean('is_supervisor'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'country_code' => ['required', 'string', 'max:10'],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_admin' => ['boolean'],
            'is_supervisor' => ['boolean'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'permissions' => ['nullable', 'array'],
            'permissions.*.module' => ['required', Rule::enum(PermissionModule::class)],
            'permissions.*.action' => ['required', Rule::enum(PermissionAction::class)],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['integer', 'exists:locations,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ];
    }
}
