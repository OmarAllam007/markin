<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Clears everything that stops an employee logging in again on a new device.
 *
 * ponytail: local development only — unbinding a device in production is the
 * admin's "Reset device" button, which leaves an audit trail this does not.
 */
class ResetEmployeeLogin extends Command
{
    protected $signature = 'employee:reset {mobile : Mobile number, with or without country code}';

    protected $description = 'Unbind the device, revoke tokens and clear the OTP rate limit (local only)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Refusing to reset logins outside the local environment.');

            return self::FAILURE;
        }

        $mobile = preg_replace('/\D/', '', (string) $this->argument('mobile'));

        $employees = Employee::query()
            ->whereRaw("REPLACE(CONCAT(mobile_country_code, mobile_number), '+', '') LIKE ?", ["%{$mobile}"])
            ->get();

        if ($employees->isEmpty()) {
            $this->error("No employee matches '{$mobile}'.");

            return self::FAILURE;
        }

        foreach ($employees as $employee) {
            $employee->tokens()->delete();
            $employee->update([
                'device_fingerprint' => null,
                'device_name' => null,
                'push_token' => null,
                'push_token_updated_at' => null,
            ]);

            RateLimiter::clear("otp-request:{$employee->mobile_country_code}|{$employee->mobile_number}");

            $this->info("Reset {$employee->id} — ".($employee->english_name ?: $employee->arabic_name)
                ." ({$employee->mobile_country_code}{$employee->mobile_number})");
        }

        $this->line('Device unbound, tokens revoked, push token cleared, OTP limit reset.');

        return self::SUCCESS;
    }
}
