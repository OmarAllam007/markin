<?php

namespace App\Models;

use App\Enums\AttendanceVia;
use App\Enums\TemporaryShiftCalculation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'company_name', 'subdomain', 'cr_number', 'company_email',
    'address', 'country', 'city', 'postal_number',
    'terms', 'policy', 'logo_path',
    'attendance_via', 'checkin_before_minutes', 'checkout_after_minutes',
    'allow_temporary_shifts', 'temporary_shift_calculation',
    'check_biometrics', 'send_reminders', 'allow_remote_checkin', 'allow_any_location_checkin',
    'timezone',
])]
class CompanySetting extends Model
{
    protected function casts(): array
    {
        return [
            'attendance_via' => AttendanceVia::class,
            'temporary_shift_calculation' => TemporaryShiftCalculation::class,
            'allow_temporary_shifts' => 'boolean',
            'check_biometrics' => 'boolean',
            'send_reminders' => 'boolean',
            'allow_remote_checkin' => 'boolean',
            'allow_any_location_checkin' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? asset('storage/'.$this->logo_path)
            : null;
    }
}
