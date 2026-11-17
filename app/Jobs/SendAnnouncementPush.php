<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\Employee;
use App\Services\FcmSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes a freshly sent announcement to its recipients' phones.
 *
 * ponytail: the recipient ids are resolved by the caller and carried in the
 * payload, so the targeting rules live in exactly one place. A tenant with tens
 * of thousands of employees should chunk this into several jobs instead.
 */
class SendAnnouncementPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<int, int> $employeeIds */
    public function __construct(
        private readonly int $announcementId,
        private readonly array $employeeIds,
    ) {}

    public function handle(FcmSender $sender): void
    {
        if (! config('services.fcm.enabled') || $this->employeeIds === []) {
            return;
        }

        $announcement = Announcement::find($this->announcementId);

        if (! $announcement) {
            return;
        }

        $body = $this->plainBody($announcement->description);

        Employee::query()
            ->whereIn('id', $this->employeeIds)
            ->whereNotNull('push_token')
            ->select(['id', 'push_token'])
            ->chunkById(100, function ($employees) use ($sender, $announcement, $body) {
                foreach ($employees as $employee) {
                    $this->push($sender, $employee, $announcement, $body);
                }
            });
    }

    private function push(FcmSender $sender, Employee $employee, Announcement $announcement, string $body): void
    {
        try {
            $delivered = $sender->send($employee->push_token, $announcement->title, $body, [
                'type' => 'announcement',
                'announcement_id' => $announcement->id,
            ]);
        } catch (Throwable $e) {
            // One unreachable phone must not stop the rest of the announcement.
            Log::warning('Announcement push failed', [
                'announcement_id' => $announcement->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! $delivered) {
            // The phone uninstalled or reset — stop pushing to a dead token.
            $employee->update(['push_token' => null]);
        }
    }

    /** Notification bodies are plain text; the description is stored as HTML. */
    private function plainBody(?string $html): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5));

        return mb_strimwidth(preg_replace('/\s+/u', ' ', $text) ?? '', 0, 180, '…');
    }
}
