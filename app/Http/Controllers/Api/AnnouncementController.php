<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $announcements = Announcement::visibleTo($employee)
            ->withExists([
                'reads as is_read' => fn ($q) => $q->where('employee_id', $employee->id),
            ])
            ->orderByDesc('sent_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Announcement $a) => [
                'id' => $a->id,
                'type' => $a->type->value,
                'title' => $a->title,
                'description' => $a->description,
                'attachment_path' => $a->attachment_path,
                'sent_at' => $a->sent_at?->format('Y-m-d H:i'),
                'is_read' => (bool) $a->is_read,
            ]);

        return ApiResponse::success('Announcements retrieved.', $announcements);
    }

    public function markRead(Request $request, Announcement $announcement): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        if (! Announcement::visibleTo($employee)->where('id', $announcement->id)->exists()) {
            return ApiResponse::error('Announcement not found.', null, 404);
        }

        AnnouncementRead::firstOrCreate([
            'announcement_id' => $announcement->id,
            'employee_id' => $employee->id,
        ]);

        return ApiResponse::success('Announcement marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $unread = Announcement::visibleTo($employee)
            ->whereDoesntHave('reads', fn ($q) => $q->where('employee_id', $employee->id))
            ->pluck('id');

        $rows = $unread->map(fn ($id) => [
            'announcement_id' => $id,
            'employee_id' => $employee->id,
            'read_at' => now(),
        ])->all();

        AnnouncementRead::insert($rows);

        return ApiResponse::success('All announcements marked as read.');
    }
}
