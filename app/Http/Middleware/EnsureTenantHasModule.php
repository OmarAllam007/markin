<?php

namespace App\Http\Middleware;

use App\Enums\AppModule;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantHasModule
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenant = app()->bound(Tenant::class) ? app(Tenant::class) : null;

        if (! $tenant || ! $tenant->hasModule(AppModule::from($module))) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This module is not available for your account.'], 403);
            }

            return redirect()->route('home')->with('error', 'This module is not enabled for your account.');
        }

        return $next($request);
    }
}
