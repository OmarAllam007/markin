<?php

namespace App\Jobs;

use App\Enums\AttendanceSource;
use App\Enums\PunchType;
use App\Models\Employee;
use App\Models\ZkRawLog;
use App\Services\AttendancePunchService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessZkPunchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $zkRawLogId) {}

    public function handle(AttendancePunchService $service): void
    {
        $log = ZkRawLog::findOrFail($this->zkRawLogId);

        if ($log->processed_at !== null) {
            return;
        }

        $machine = $log->machine;

        $employee = Employee::where('tenant_id', $machine->tenant_id)
            ->where('biometric_id', $log->employee_code)
            ->first();

        if (! $employee) {
            $log->update(['error_message' => "No employee found with biometric_id [{$log->employee_code}]."]);

            return;
        }

        $punchType = match ($log->punch_status) {
            0, 4 => PunchType::CheckIn,
            default => PunchType::CheckOut,
        };

        $service->punchFromBiometric(
            employee: $employee,
            type: $punchType,
            punchedAt: Carbon::instance($log->punched_at),
            source: AttendanceSource::Biometric,
            deviceName: $machine->name ?? $machine->serial_number,
        );

        $log->update(['processed_at' => now(), 'error_message' => null]);
    }

    public function failed(Throwable $exception): void
    {
        ZkRawLog::where('id', $this->zkRawLogId)
            ->update(['error_message' => $exception->getMessage()]);
    }
}
