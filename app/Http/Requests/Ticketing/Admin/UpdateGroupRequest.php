<?php

namespace App\Http\Requests\Ticketing\Admin;

use App\Enums\TicketGroupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'type' => ['required', new Enum(TicketGroupType::class)],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', Rule::exists('tenant_user', 'user_id')->where('tenant_id', $this->user()->current_tenant_id)],
        ];
    }
}
