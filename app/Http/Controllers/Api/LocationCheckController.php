<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckLocationRequest;
use App\Models\Employee;
use App\Services\AttendancePunchService;
use Illuminate\Http\JsonResponse;

class LocationCheckController extends Controller
{
    public function __construct(public AttendancePunchService $service) {}

    public function check(CheckLocationRequest $request): JsonResponse
    {

        /** @var Employee $employee */
        $employee = $request->user();

        $result = $this->service->detectLocation(
            employee: $employee,
            latitude: (float) $request->latitude,
            longitude: (float) $request->longitude,
        );

        return ApiResponse::success($result['message'], [
            'is_within_location' => $result['is_within_location'],
            'location_name' => $result['location_name'],
        ]);
    }
}
