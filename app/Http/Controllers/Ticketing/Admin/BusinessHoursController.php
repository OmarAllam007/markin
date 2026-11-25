<?php

namespace App\Http\Controllers\Ticketing\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Services\BusinessCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The company's working week, used by SLAs marked "business hours only". */
class BusinessHoursController extends Controller
{
    public function edit(Request $request): Response
    {
        $tenant = $this->authorizeAdmin($request, PermissionAction::View);

        return Inertia::render('ticketing/admin/business-hours/Edit', [
            'hours' => $tenant->settings?->business_hours ?? BusinessCalendar::DEFAULT_HOURS,
            'timezone' => $tenant->settings?->timezone ?: 'UTC',
            'holidaysCount' => Holiday::where('tenant_id', $tenant->id)->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $this->authorizeAdmin($request);
        $data = $request->validate([
            'hours' => ['required', 'array'],
            'hours.*' => ['nullable', 'array', 'size:2'],
            'hours.*.*' => ['required', 'date_format:H:i'],
        ]);

        $hours = [];
        foreach (range(0, 6) as $day) {
            $window = $data['hours'][$day] ?? $data['hours'][(string) $day] ?? null;
            if ($window && $window[1] <= $window[0]) {
                throw ValidationException::withMessages(["hours.{$day}" => 'The closing time must be after the opening time.']);
            }
            $hours[(string) $day] = $window ? array_values($window) : null;
        }

        if (! array_filter($hours)) {
            throw ValidationException::withMessages(['hours' => 'Open at least one day.']);
        }

        CompanySetting::updateOrCreate(['tenant_id' => $tenant->id], ['business_hours' => $hours]);

        return back()->with('success', 'Working hours saved. They apply to new requests and to the clock from now on.');
    }

    private function authorizeAdmin(Request $request, PermissionAction $action = PermissionAction::Edit): Tenant
    {
        $tenantId = $request->user()->current_tenant_id;
        abort_if($tenantId === null, 403);
        $tenant = Tenant::findOrFail($tenantId);
        abort_unless($request->user()->canPerform($tenant, PermissionModule::Ticketing, $action), 403, 'You do not have permission to perform this action.');

        return $tenant;
    }
}
