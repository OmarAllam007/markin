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

        if ($employee instanceof Employee && $employee->status !== EmployeeStatus::Active) {
            $employee->currentAccessToken()?->delete();

            return ApiResponse::error('Your account is not active. Please contact your administrator.', null, 403);
        }

        return $next($request);
    }
}
