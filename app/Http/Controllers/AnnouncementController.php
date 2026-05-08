<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnouncementRequest;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('announcements/Index', ['announcements' => []]);
        }

        $announcements = Announcement::query()
            ->where('tenant_id', $tenantId)
            ->with(['creator:id,name', 'targetLocation:id,name', 'targetDepartment:id,name'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('announcements/Index', [
            'announcements' => $announcements,
        ]);
    }

    public function create(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer'],
        ]);

        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return Inertia::render('announcements/Create', [
                'options' => ['locations' => [], 'departments' => []],
                'employees' => [],
                'filters' => ['search' => null],
            ]);
        }

        $employees = Employee::query()
            ->where('tenant_id', $tenantId)
            ->with(['department:id,name', 'location:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('english_name', 'like', "%{$s}%")
                    ->orWhere('arabic_name', 'like', "%{$s}%")
                    ->orWhere('employee_number', 'like', "%{$s}%");
            }))
            ->orderBy('english_name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('announcements/Create', [
            'options' => [
                'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            ],
            'employees' => $employees,
            'filters' => ['search' => $request->input('search')],
        ]);
    }

    public function show(Request $request, Announcement $announcement): Response
    {
        $this->authorizesTenantAccess($request, $announcement->tenant_id);

        $announcement->load(['creator:id,name', 'targetLocation:id,name', 'targetDepartment:id,name']);

        $targetEmployees = $this->resolveTargetEmployees($announcement, $announcement->tenant_id)
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'employee_number' => $e->employee_number,
                'english_name' => $e->english_name,
                'arabic_name' => $e->arabic_name,
                'email' => $e->email,
                'job_title_en' => $e->job_title_en,
                'department' => $e->department?->only(['id', 'name']),
                'location' => $e->location?->only(['id', 'name']),
            ])
            ->values();

        return Inertia::render('announcements/Show', [
            'announcement' => $announcement,
            'targetEmployees' => $targetEmployees,
        ]);
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizesTenantAccess($request, $announcement->tenant_id);

        if ($announcement->attachment_path) {
            Storage::delete($announcement->attachment_path);
        }

        $announcement->delete();

        return redirect()->route('announcements.index')
            ->with('success', 'Announcement deleted successfully.');
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $this->authorizesTenantAccess($request, $tenantId);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('announcements');
        }

        $validated = $request->validated();

        $announcement = Announcement::create([
            'tenant_id' => $tenantId,
            'created_by' => $request->user()->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'target_type' => $validated['target_type'],
            'target_location_id' => $validated['target_location_id'] ?? null,
            'target_department_id' => $validated['target_department_id'] ?? null,
            'target_employee_ids' => $validated['target_employee_ids'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);

        $employees = $this->resolveTargetEmployees($announcement, $tenantId);
        $recipientsCount = $employees->count();

        $announcement->update([
            'recipients_count' => $recipientsCount,
            'sent_at' => now(),
        ]);

        foreach ($employees->filter(fn (Employee $e) => ! empty($e->email)) as $employee) {
            Mail::to($employee->email)->queue(new AnnouncementMail($announcement, $employee));
        }

        return redirect()->route('announcements.index')
            ->with('success', "Announcement sent to {$recipientsCount} employee(s).");
    }

    private function resolveTargetEmployees(Announcement $announcement, int $tenantId): Collection
    {
        $query = Employee::query()->where('tenant_id', $tenantId);

        if ($announcement->target_type === 'employees') {
            $query->whereIn('id', $announcement->target_employee_ids ?? []);
        } else {
            $locationId = $announcement->target_location_id;
            $departmentId = $announcement->target_department_id;

            if ($locationId !== null && $departmentId !== null) {
                $query->where(function ($q) use ($locationId, $departmentId) {
                    $q->where('location_id', $locationId)
                        ->orWhere('department_id', $departmentId);
                });
            } elseif ($locationId !== null) {
                $query->where('location_id', $locationId);
            } elseif ($departmentId !== null) {
                $query->where('department_id', $departmentId);
            }
            // Both null = all employees in the tenant
        }

        return $query->get();
    }

    private function authorizesTenantAccess(Request $request, int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        if (! $request->user()->canAccessTenant($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }
    }
}
