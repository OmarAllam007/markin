<?php

namespace App\Http\Requests\Ticketing;

use App\Enums\TicketApprovalAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTicketApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', new Enum(TicketApprovalAction::class)],
            'comments' => ['nullable', 'string', 'max:2000'],
            'delegated_to_user_id' => ['nullable', 'integer', 'exists:users,id', 'required_if:action,delegated'],
        ];
    }
}
