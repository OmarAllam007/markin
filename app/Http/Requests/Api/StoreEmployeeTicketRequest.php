<?php

namespace App\Http\Requests\Api;

use App\Services\TicketService;
use Illuminate\Foundation\Http\FormRequest;

/** Ticket submitted by an employee from the mobile app; the module gate runs in middleware. */
class StoreEmployeeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return app(TicketService::class)->submissionRules($this->all(), $this->user()->tenant_id);
    }
}
