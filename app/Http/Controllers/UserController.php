<?php

namespace App\Http\Controllers;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserStatus;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Department;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);

        $currentUser = $request->user();
        $accessibleTenants = $currentUser->switchableTenants();
        $accessibleTenantIds = $accessibleTenants->pluck('id');

        $tenantId = $request->filled('tenant_id')
            ? $request->integer('tenant_id')
            : $currentUser->current_tenant_id;

        if (! $accessibleTenantIds->contains($tenantId)) {
            $tenantId = $currentUser->current_tenant_id ?? $accessibleTenantIds->first();
        }

        $users = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->with(['tenants' => fn ($q) => $q->where('tenants.id', $tenantId)])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function (User $user) {
                $membership = $user->tenants->first()?->pivot;
                $user->is_admin = $membership?->is_admin ?? false;
                $user->is_supervisor = $membership?->is_supervisor ?? false;
                $user->status = $membership?->status?->value ?? UserStatus::Active->value;
                $user->tenant = $user->tenants->first();

                return $user;
            });

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->input('search'),
                'tenant_id' => $tenantId,
            ],
            'tenants' => $accessibleTenants->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $request->user()->current_tenant_id;

        return Inertia::render('users/Create', [
            'statuses' => $this->statusOptions(),
            'permissionModules' => $this->permissionModuleOptions(),
            'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tenantId = $request->user()->current_tenant_id;

        if (! $tenantId) {
            return redirect()->back()->with('error', 'No active company selected.');
        }

        $this->authorizesTenantAccess($request, $tenantId);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'country_code' => $data['country_code'],
            'mobile' => $data['mobile'],
            'password' => $data['password'],
            'current_tenant_id' => $tenantId,
        ]);

        $user->tenants()->attach($tenantId, [
            'is_admin' => $data['is_admin'] ?? false,
            'is_supervisor' => $data['is_supervisor'] ?? false,
            'status' => $data['status'],
        ]);

        $this->syncPermissions($user, $tenantId, $data['permissions'] ?? []);
        $this->syncLocationAccess($user, $tenantId, $data['location_ids'] ?? []);
        $this->syncDepartmentAccess($user, $tenantId, $data['department_ids'] ?? []);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): Response
    {
        $currentUser = request()->user();
        $tenantId = $currentUser->current_tenant_id;
        $membership = $tenantId
            ? $user->tenants()->where('tenants.id', $tenantId)->first()
            : null;

        $userPermissions = $tenantId
            ? $user->permissions()->where('tenant_id', $tenantId)->get(['module', 'action'])
                ->map(fn ($p) => ['module' => $p->module->value, 'action' => $p->action->value])
                ->values()
            : collect();

        $userLocationIds = $tenantId
            ? $user->locationAccess()->wherePivot('tenant_id', $tenantId)->pluck('locations.id')
            : collect();

        $userDepartmentIds = $tenantId
            ? $user->departmentAccess()->wherePivot('tenant_id', $tenantId)->pluck('departments.id')
            : collect();

        return Inertia::render('users/Edit', [
            'user' => array_merge($user->toArray(), [
                'is_admin' => $membership?->pivot->is_admin ?? false,
                'is_supervisor' => $membership?->pivot->is_supervisor ?? false,
                'status' => $membership?->pivot->status?->value ?? UserStatus::Active->value,
                'permissions' => $userPermissions,
                'location_ids' => $userLocationIds->values(),
                'department_ids' => $userDepartmentIds->values(),
            ]),
            'isSelf' => $user->id === $currentUser->id,
            'statuses' => $this->statusOptions(),
            'permissionModules' => $this->permissionModuleOptions(),
            'locations' => Location::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $tenantId = $request->user()->current_tenant_id;
        $isSelf = $user->id === $request->user()->id;

        $userFields = Arr::only($data, ['email', 'country_code', 'mobile']);
        if (! $isSelf) {
            $userFields['name'] = $data['name'];
        }
        if (! empty($data['password'])) {
            $userFields['password'] = $data['password'];
        }

        $user->update($userFields);

        if (! $isSelf && $tenantId) {
            $this->authorizesTenantAccess($request, $tenantId);

            $user->tenants()->syncWithoutDetaching([
                $tenantId => [
                    'is_admin' => $data['is_admin'] ?? false,
                    'is_supervisor' => $data['is_supervisor'] ?? false,
                    'status' => $data['status'],
                ],
            ]);

            $this->syncPermissions($user, $tenantId, $data['permissions'] ?? []);
            $this->syncLocationAccess($user, $tenantId, $data['location_ids'] ?? []);
            $this->syncDepartmentAccess($user, $tenantId, $data['department_ids'] ?? []);
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    private function authorizesTenantAccess(Request $request, int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        if (! $request->user()->canAccessTenant($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }
    }

    /** @return array<int, array{value: string, label: string, color: string}> */
    private function statusOptions(): array
    {
        return collect(UserStatus::cases())->map(fn ($s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ])->all();
    }

    /** @return array<int, array{key: string, label: string, actions: array<int, array{value: string, label: string}>}> */
    private function permissionModuleOptions(): array
    {
        return collect(PermissionModule::cases())->map(fn (PermissionModule $module) => [
            'key' => $module->value,
            'label' => $module->label(),
            'actions' => collect($module->allowedActions())->map(fn (PermissionAction $action) => [
                'value' => $action->value,
                'label' => $action->label(),
            ])->all(),
        ])->all();
    }

    /** @param array<int, array{module: string, action: string}> $permissions */
    private function syncPermissions(User $user, int $tenantId, array $permissions): void
    {
        $user->permissions()->where('tenant_id', $tenantId)->delete();

        $records = collect($permissions)->map(fn ($p) => [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'module' => $p['module'],
            'action' => $p['action'],
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if (! empty($records)) {
            UserPermission::insert($records);
        }
    }

    /** @param array<int, int> $locationIds */
    private function syncLocationAccess(User $user, int $tenantId, array $locationIds): void
    {
        $user->locationAccess()->wherePivot('tenant_id', $tenantId)->detach();

        $sync = collect($locationIds)->mapWithKeys(fn ($id) => [
            $id => ['tenant_id' => $tenantId],
        ])->all();

        if (! empty($sync)) {
            $user->locationAccess()->attach($sync);
        }
    }

    /** @param array<int, int> $departmentIds */
    private function syncDepartmentAccess(User $user, int $tenantId, array $departmentIds): void
    {
        $user->departmentAccess()->wherePivot('tenant_id', $tenantId)->detach();

        $sync = collect($departmentIds)->mapWithKeys(fn ($id) => [
            $id => ['tenant_id' => $tenantId],
        ])->all();

        if (! empty($sync)) {
            $user->departmentAccess()->attach($sync);
        }
    }
}
