<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $unreadCount = EmployeeNotification::where('employee_id', $employee->id)
            ->unread()
            ->count();

        $notifications = EmployeeNotification::where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return ApiResponse::success('Notifications retrieved.', [
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, EmployeeNotification $notification): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if ((int) $notification->employee_id !== $employee->id) {
            return ApiResponse::error('Notification not found.', null, 404);
        }

        $notification->markAsRead();

        return ApiResponse::success('Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        EmployeeNotification::where('employee_id', $employee->id)
            ->unread()
            ->update(['read_at' => now()]);

        return ApiResponse::success('All notifications marked as read.');
    }
}
