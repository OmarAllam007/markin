<?php

namespace App\Jobs;

use App\Models\EmployeeNotification;
use App\Services\FcmSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendEmployeePush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly int $notificationId) {}

    public function handle(FcmSender $sender): void
    {
        if (! config('services.fcm.enabled')) {
            return;
        }

        $notification = EmployeeNotification::with('employee:id,push_token')->find($this->notificationId);
        $token = $notification?->employee?->push_token;

        if (! $notification || ! $token) {
            return;
        }

        $delivered = $sender->send($token, $notification->title, $notification->body ?? '', [
            'notification_id' => $notification->id,
            'type' => $notification->type->value,
        ]);

        if (! $delivered) {
            // The phone uninstalled or reset — stop pushing to a dead token.
            $notification->employee->update(['push_token' => null]);
            Log::info('Cleared a dead push token', ['employee_id' => $notification->employee_id]);
        }
    }
}
