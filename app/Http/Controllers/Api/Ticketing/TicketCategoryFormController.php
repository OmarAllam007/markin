<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TicketCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCategoryFormController extends Controller
{
    public function __invoke(Request $request, TicketCategory $category): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if ($category->tenant_id !== $employee->tenant_id) {
            return ApiResponse::error('Not found.', null, 404);
        }

        return ApiResponse::success('Form fields retrieved.', TicketCategoryController::buildFormPayload($category, $employee->tenant_id));
    }
}
