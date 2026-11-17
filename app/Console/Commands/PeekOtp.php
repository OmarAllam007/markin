<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Prints the OTP codes currently live in the cache.
 *
 * ponytail: local development only — it scans the cache rather than adding a
 * debug route, so nothing reachable from the network can ever leak a code.
 */
class PeekOtp extends Command
{
    protected $signature = 'otp:peek';

    protected $description = 'Show the OTP codes currently waiting to be verified (local only)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Refusing to print OTP codes outside the local environment.');

            return self::FAILURE;
        }

        $connection = Redis::connection(config('cache.stores.redis.connection', 'cache'));

        // keys() returns the fully-prefixed name, but every other command
        // re-applies the client prefix — so strip only that one back off.
        $clientPrefix = (string) config('database.redis.options.prefix');

        $keys = $connection->keys('*otp:token:*');

        if (empty($keys)) {
            $this->warn('No OTP is waiting. Request one from the app first — codes expire after 5 minutes.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($keys as $key) {
            // Redis returns the key with the client prefix already applied.
            $token = substr($key, strrpos($key, 'otp:token:') + strlen('otp:token:'));
            $session = Cache::get("otp:token:{$token}");

            if (! $session) {
                continue;
            }

            $employee = Employee::find($session['employee_id']);

            $rows[] = [
                $session['code'],
                $session['employee_id'],
                $employee?->english_name ?: $employee?->arabic_name ?: '?',
                $employee ? $employee->mobile_country_code.$employee->mobile_number : '?',
                max($connection->ttl(preg_replace('/^'.preg_quote($clientPrefix, '/').'/', '', $key)), 0).'s',
            ];
        }

        $this->table(['code', 'employee', 'name', 'mobile', 'expires in'], $rows);

        return self::SUCCESS;
    }
}
