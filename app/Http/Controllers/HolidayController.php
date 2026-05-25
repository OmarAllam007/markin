<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Models\Holiday;
use App\Models\Tenant;
use App\Services\HolidayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function __construct(private readonly HolidayService $holidayService) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('holidays/Index', [
                'holidays' => [],
                'filters' => ['year' => now()->year],
            ]);
        }

        $this->authorizesTenantAccess($request, $tenantId);

        $year = (int) ($request->input('year') ?? now()->year);

        $holidays = Holiday::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($year) {
                $q->whereYear('date', $year)
                    ->orWhere('is_recurring', true);
            })
            ->with('creator:id,name')
            ->orderBy('date')
            ->get();

        return Inertia::render('holidays/Index', [
            'holidays' => $holidays->map(fn (Holiday $h) => [
                'id' => $h->id,
                'name' => $h->name,
                'date' => $h->date->toDateString(),
                'is_recurring' => $h->is_recurring,
                'creator' => $h->creator,
            ]),
            'filters' => ['year' => $year],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('holidays/Create');
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $this->authorizesTenantAccess($request, $tenantId);

        $data = $request->validated();

        $holiday = Holiday::create([
            'tenant_id' => $tenantId,
            'created_by' => $request->user()->id,
            'name' => $data['name'],
            'date' => $data['date'],
            'is_recurring' => (bool) ($data['is_recurring'] ?? false),
        ]);

        $updated = $this->holidayService->applyToExistingAttendances($holiday);

        $message = 'Holiday added successfully.';
        if ($updated > 0) {
            $message .= " {$updated} attendance record(s) updated to Holiday.";
        }

        return redirect()->route('holidays.index')->with('success', $message);
    }

    public function edit(Request $request, Holiday $holiday): Response
    {
        $this->authorizesTenantAccess($request, $holiday->tenant_id);

        return Inertia::render('holidays/Edit', [
            'holiday' => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'date' => $holiday->date->toDateString(),
                'is_recurring' => $holiday->is_recurring,
            ],
        ]);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $holiday->tenant_id);

        $data = $request->validated();

        $dateChanged = $holiday->date->toDateString() !== $data['date']
            || $holiday->is_recurring !== (bool) ($data['is_recurring'] ?? false);

        if ($dateChanged) {
            // Revert old coverage before applying new coverage.
            $this->holidayService->revertFromAttendances($holiday);
        }

        $holiday->update([
            'name' => $data['name'],
            'date' => $data['date'],
            'is_recurring' => (bool) ($data['is_recurring'] ?? false),
        ]);

        $updated = $this->holidayService->applyToExistingAttendances($holiday);

        $message = 'Holiday updated successfully.';
        if ($updated > 0) {
            $message .= " {$updated} attendance record(s) updated to Holiday.";
        }

        return redirect()->route('holidays.index')->with('success', $message);
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $holiday->tenant_id);

        // Soft-delete first so isHoliday() no longer finds this holiday
        // when checking whether another holiday still covers the same date.
        $holiday->delete();

        $reverted = $this->holidayService->revertFromAttendances($holiday);

        $message = 'Holiday deleted.';
        if ($reverted > 0) {
            $message .= " {$reverted} attendance record(s) reverted to Absent.";
        }

        return redirect()->route('holidays.index')->with('success', $message);
    }

    private function authorizesTenantAccess(Request $request, int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        if (! $request->user()->canAccessTenant($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }
    }
}
