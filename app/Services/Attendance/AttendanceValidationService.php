<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Validation\ValidationException;

/**
 * Guards domain rules before persistence (locks, tenant boundaries, shift ownership).
 */
final class AttendanceValidationService
{
    /**
     * @throws ValidationException
     */
    public function assertCanModify(User $user, Attendance $attendance): void
    {
        if ($attendance->is_locked) {
            throw ValidationException::withMessages([
                'attendance' => __('This attendance record is locked and cannot be changed.'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertEmployeeBelongsToTenant(Employee $employee, int $tenantId): void
    {
        if ((int) $employee->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'employee_id' => __('Employee does not belong to the selected company.'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertShiftBelongsToTenant(?WorkShift $shift, int $tenantId): void
    {
        if ($shift !== null && (int) $shift->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'shift_id' => __('Shift does not belong to the selected company.'),
            ]);
        }
    }
}
