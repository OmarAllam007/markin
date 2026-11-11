<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveRequestStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLeaveRequestRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct(private readonly LeaveService $leaveService) {}

    /** Balances and my requests in one call — the app shows them on one screen. */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $year = (int) ($request->integer('year') ?: now()->year);

        $types = LeaveType::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $balances = $types->map(fn (LeaveType $type) => [
            'leave_type_id' => $type->id,
            'name' => $type->name,
            'is_paid' => $type->is_paid,
        ] + $this->leaveService->balance($employee, $type, $year))->all();

        $requests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->with('leaveType:id,name,is_paid')
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        return ApiResponse::success('Leaves retrieved.', [
            'year' => $year,
            'balances' => $balances,
            'requests' => $requests,
        ]);
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $data = $request->validated();

        $type = LeaveType::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('is_active', true)
            ->find($data['leave_type_id']);

        if (! $type) {
            return ApiResponse::error('Leave type not found.', ['leave_type_id' => ['Leave type not found.']], 422);
        }

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);

        if ($this->leaveService->overlaps($employee, $start, $end)) {
            return ApiResponse::error('You already have a leave request covering these dates.', [
                'start_date' => ['You already have a leave request covering these dates.'],
            ], 422);
        }

        $days = count($this->leaveService->workingDays($employee, $start, $end));

        if ($days === 0) {
            return ApiResponse::error('These dates are all weekends or holidays.', [
                'start_date' => ['These dates are all weekends or holidays.'],
            ], 422);
        }

        $balance = $this->leaveService->balance($employee, $type, (int) $start->year);

        if ($balance['remaining'] !== null && $days > $balance['remaining']) {
            return ApiResponse::error("Not enough balance: {$balance['remaining']} day(s) left.", [
                'leave_type_id' => ["Not enough balance: {$balance['remaining']} day(s) left."],
            ], 422);
        }

        $leaveRequest = LeaveRequest::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => LeaveRequestStatus::Pending,
        ]);

        return ApiResponse::success('Leave request submitted.', [
            'id' => $leaveRequest->id,
            'days' => $days,
            'status' => $leaveRequest->status->value,
        ], 201);
    }
}
