<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendEmployeeOtpSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $mobile,
        public readonly string $code,
    ) {}

    public function handle(): void
    {
        $config = config('services.otp.sms');

        if (! $config['url']) {
            Log::warning('OTP SMS skipped: no provider configured.', ['mobile' => $this->mobile]);

            return;
        }

        // ponytail: generic {to, message} POST with a bearer token — fits most Arab SMS
        // gateways (Unifonic, Taqnyat, 4Jawaly). Remap these 3 keys when the provider is picked.
        Http::asJson()
            ->withToken((string) $config['token'])
            ->timeout(15)
            ->post($config['url'], array_filter([
                'to' => $this->mobile,
                'sender' => $config['sender'],
                'message' => str_replace(':code', $this->code, $config['template']),
            ]))
            ->throw();
    }
}
