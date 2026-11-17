<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\FcmSender;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sends a real FCM push to one employee's registered device.
 *
 * ponytail: deliberately bypasses EmployeeNotification and the queue — this
 * tests the FCM leg only, so a failure here is always the Firebase setup and
 * never the notification pipeline.
 */
class SendTestPush extends Command
{
    protected $signature = 'push:test
                            {employee? : Employee id, or part of a name/mobile}
                            {--title=Test push}
                            {--body=If you can read this, FCM works.}';

    protected $description = 'Send a test push notification to an employee device';

    public function handle(FcmSender $sender): int
    {
        if (! config('services.fcm.enabled')) {
            $this->warn('PUSH_ENABLED is false — sending anyway, since you asked for a test.');
        }

        $employee = $this->resolve($this->argument('employee'));

        if (! $employee) {
            return self::FAILURE;
        }

        $this->line("Employee : {$employee->id} — ".($employee->english_name ?: $employee->arabic_name));
        $this->line('Token    : '.substr($employee->push_token, 0, 24).'… ('.strlen($employee->push_token).' chars)');
        $this->line('Saved at : '.($employee->push_token_updated_at ?? 'unknown'));
        $this->newLine();

        try {
            $delivered = $sender->send(
                $employee->push_token,
                (string) $this->option('title'),
                (string) $this->option('body'),
                ['notification_id' => '0', 'type' => 'test'],
            );
        } catch (Throwable $e) {
            $this->error('FCM refused the request: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $delivered) {
            $this->error('FCM rejected the token — the install is gone, or it belongs to another Firebase project.');

            return self::FAILURE;
        }

        $this->info('Accepted by FCM. It should appear on the device within a second or two.');

        return self::SUCCESS;
    }

    private function resolve(?string $needle): ?Employee
    {
        $query = Employee::query()->whereNotNull('push_token');

        if ($needle !== null) {
            // An exact id wins outright: a mobile number contains short ids as a
            // substring, so a text search would quietly pick the wrong person.
            $employee = ctype_digit($needle)
                ? (clone $query)->find((int) $needle)
                : null;

            $employee ??= (clone $query)
                ->where(fn ($q) => $q
                    ->where('mobile_number', 'like', "%{$needle}%")
                    ->orWhere('english_name', 'like', "%{$needle}%")
                    ->orWhere('arabic_name', 'like', "%{$needle}%"))
                ->first();

            if ($employee) {
                return $employee;
            }

            $this->error("No employee with a registered push token matches '{$needle}'.");
        }

        $candidates = $query->get(['id', 'english_name', 'arabic_name', 'mobile_number', 'push_token_updated_at']);

        if ($candidates->isEmpty()) {
            $this->error('No employee has registered a push token yet.');
            $this->line('Log in on the device first — the app posts its token to employee/push-token.');

            return null;
        }

        $this->line('Employees with a registered device:');
        $this->table(
            ['id', 'name', 'mobile', 'token saved at'],
            $candidates->map(fn ($e) => [
                $e->id,
                $e->english_name ?: $e->arabic_name,
                $e->mobile_number,
                $e->push_token_updated_at,
            ]),
        );
        $this->line('Pick one: php artisan push:test <id>');

        return null;
    }
}
