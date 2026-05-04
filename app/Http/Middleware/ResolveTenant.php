<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->current_tenant_id) {
            $tenant = $user->currentTenant;
            if ($tenant) {
                app()->instance(Tenant::class, $tenant);
            }
        }

        return $next($request);
    }
}
