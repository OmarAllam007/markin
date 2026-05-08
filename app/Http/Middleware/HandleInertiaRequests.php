<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = $user?->currentTenant;
        $settings = $tenant?->settings;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'currentTenant' => $tenant ? [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'parent_id' => $tenant->parent_id,
                    'settings' => $settings ? [
                        'company_name' => $settings->company_name,
                        'subdomain' => $settings->subdomain,
                        'cr_number' => $settings->cr_number,
                        'company_email' => $settings->company_email,
                        'address' => $settings->address,
                        'country' => $settings->country,
                        'city' => $settings->city,
                        'postal_number' => $settings->postal_number,
                        'terms' => $settings->terms,
                        'policy' => $settings->policy,
                        'logo_url' => $settings->logoUrl(),
                        'attendance_via' => $settings->attendance_via?->value ?? 'all',
                        'checkin_before_minutes' => $settings->checkin_before_minutes,
                        'checkout_after_minutes' => $settings->checkout_after_minutes,
                        'allow_temporary_shifts' => $settings->allow_temporary_shifts,
                        'temporary_shift_calculation' => $settings->temporary_shift_calculation?->value,
                        'check_biometrics' => $settings->check_biometrics,
                        'send_reminders' => $settings->send_reminders,
                        'allow_remote_checkin' => $settings->allow_remote_checkin,
                        'allow_any_location_checkin' => $settings->allow_any_location_checkin,
                    ] : null,
                ] : null,
                'switchableTenants' => $user ? $user->switchableTenants()->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'parent_id' => $t->parent_id,
                ])->values() : [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'import_errors' => $request->session()->get('import_errors', []),
            ],
        ];
    }
}
