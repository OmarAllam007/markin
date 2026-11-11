<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging over the HTTP v1 API.
 *
 * ponytail: the only thing a client library would add here is an OAuth2 token
 * exchange, which is one signed JWT — so no SDK, no 15 transitive packages.
 */
class FcmSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * True when the push actually went out. A dead token is cleared by the caller.
     *
     * @param  array<string, string>  $data
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        $credentials = $this->credentials();

        $response = Http::withToken($this->accessToken($credentials))
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    // FCM only carries string values, so everything is cast.
                    'data' => array_map(fn ($v) => (string) $v, $data),
                    'android' => ['priority' => 'high'],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        // UNREGISTERED / INVALID_ARGUMENT means the token is dead, not that we are broken.
        if (in_array($response->status(), [400, 404], true)) {
            Log::info('FCM rejected a token', ['status' => $response->status(), 'body' => $response->json()]);

            return false;
        }

        $response->throw();

        return false;
    }

    /** @param array{client_email: string, private_key: string, project_id: string} $credentials */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm:access_token', now()->addMinutes(55), function () use ($credentials) {
            $now = time();
            $claims = [
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $payload = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
                .'.'.$this->base64Url(json_encode($claims));

            if (! openssl_sign($payload, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign the FCM service-account JWT.');
            }

            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $payload.'.'.$this->base64Url($signature),
            ])->throw();

            return $response->json('access_token');
        });
    }

    /** @return array{client_email: string, private_key: string, project_id: string} */
    private function credentials(): array
    {
        $path = config('services.fcm.credentials');

        if (! $path || ! is_file($path)) {
            throw new RuntimeException('FCM_CREDENTIALS does not point at a service-account JSON file.');
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        foreach (['client_email', 'private_key', 'project_id'] as $key) {
            if (empty($credentials[$key])) {
                throw new RuntimeException("The FCM service-account JSON is missing {$key}.");
            }
        }

        return $credentials;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
