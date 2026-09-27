<?php

namespace App\Http\Middleware;

use App\Enums\AppModule;
use App\Helpers\ApiResponse;
use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiEmployeeTenantHasModule
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        /** @var Employee|null $employee */
        $employee = $request->user();

        if (! $employee || ! $employee->tenant->hasModule(AppModule::from($module))) {
            return ApiResponse::error('This module is not available for your account.', null, 403);
        }

        return $next($request);
    }
}
