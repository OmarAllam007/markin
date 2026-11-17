<?php

namespace App\Http\Middleware;

use App\Enums\EmployeeStatus;
use App\Helpers\ApiResponse;
use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backstop for status changes that skip model events (bulk updates, raw SQL):
 * a non-active employee's token is revoked on first use.
 */
class EnsureApiEmployeeIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user();

        // auth:sanctum falls back to the session guard for browser requests, so a
        // signed-in admin User reaches these routes without a Bearer token. Every
        // employee/* route is employee-only, so anything else is turned away here.
        if (! $employee instanceof Employee) {
            return ApiResponse::error('This endpoint is for employee accounts only.', null, 403);
        }

        if ($employee->status !== EmployeeStatus::Active) {
            $employee->currentAccessToken()?->delete();

            return ApiResponse::error('Your account is not active. Please contact your administrator.', null, 403);
        }

        return $next($request);
    }
}
