<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'coordinates' => ['required', 'array'],
            'coordinates.north' => ['required', 'numeric', 'between:-90,90'],
            'coordinates.south' => ['required', 'numeric', 'between:-90,90'],
            'coordinates.east' => ['required', 'numeric', 'between:-180,180'],
            'coordinates.west' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
