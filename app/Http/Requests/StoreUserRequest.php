<?php

namespace App\Http\Requests;

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
//            'is_admin' => $this->boolean('is_admin'),
//            'is_supervisor' => $this->boolean('is_supervisor'),
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
//            'is_admin' => ['boolean'],
//            'is_supervisor' => ['boolean'],
//            'preferred_theme' => ['required', Rule::in(['light', 'dark'])],
//            'preferred_language' => ['required', 'string', 'max:10'],
//            'status' => ['required', Rule::enum(UserStatus::class)],
        ];
    }
}
