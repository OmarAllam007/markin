<?php

namespace App\Http\Requests\Ticketing\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSlaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'first_response_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'resolve_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'business_hours_only' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
