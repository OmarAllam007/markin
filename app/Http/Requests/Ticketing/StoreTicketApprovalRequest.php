<?php

namespace App\Http\Requests\Ticketing;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketApprovalRequest extends FormRequest
{
    /** The ticket's technician or a company admin. */
    public function authorize(): bool
    {
        return self::allows($this->user(), $this->route('ticket'));
    }

    public static function allows(User $user, Ticket $ticket): bool
    {
        return $ticket->tenant_id === $user->current_tenant_id
            && ($ticket->technician_id === $user->id || $user->isAdminOf(Tenant::findOrFail($ticket->tenant_id)));
    }

    public function rules(): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->route('ticket');
        $lastStage = (int) $ticket->approvals()->max('stage_order');

        return [
            'approver_employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $ticket->tenant_id)->whereNotNull('email')->where('email', '!=', '')],
            // First approval is always stage 1; afterwards an existing stage or a new one.
            'new_stage' => ['boolean'],
            'stage_order' => [Rule::requiredIf($lastStage > 0 && ! $this->boolean('new_stage')), 'nullable', 'integer', 'min:1', "max:{$lastStage}"],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:10240'],
        ];
    }

    public function stage(): int
    {
        $lastStage = (int) $this->route('ticket')->approvals()->max('stage_order');

        return $lastStage === 0 || $this->boolean('new_stage') ? $lastStage + 1 : (int) $this->validated('stage_order');
    }

    public function messages(): array
    {
        return ['approver_employee_id.exists' => 'Pick an employee of this company who has an email address.'];
    }
}
