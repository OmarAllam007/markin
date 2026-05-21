<?php

namespace App\Http\Controllers\Api;

use App\Enums\PunchType;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePunchRequest;
use App\Models\Employee;
use App\Services\AttendancePunchService;
use Illuminate\Http\JsonResponse;

class AttendancePunchController extends Controller
{
    public function __construct(public AttendancePunchService $service) {}

    public function store(StorePunchRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $punch = $this->service->punch(
            employee: $employee,
            type: PunchType::from($request->type),
            latitude: $request->latitude,
            longitude: $request->longitude,
        );

        return ApiResponse::success('Punch recorded.', [
            'punch_id' => $punch->id,
            'type' => $punch->type->value,
            'punched_at' => $punch->punched_at->toIso8601String(),
        ]);
    }
}
