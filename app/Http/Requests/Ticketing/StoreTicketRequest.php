<?php

namespace App\Http\Requests\Ticketing;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\TicketSource;
use App\Models\Tenant;
use App\Services\TicketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = Tenant::find($this->user()->current_tenant_id);

        return $tenant && $this->user()->canPerform($tenant, PermissionModule::Ticketing, PermissionAction::Create);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;

        return app(TicketService::class)->submissionRules($this->all(), $tenantId) + [
            'source' => ['nullable', new Enum(TicketSource::class)],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
