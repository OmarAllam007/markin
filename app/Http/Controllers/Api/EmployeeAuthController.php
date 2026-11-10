<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RequestEmployeeOtpRequest;
use App\Http\Requests\Api\VerifyEmployeeOtpRequest;
use App\Mail\EmployeeOtpMail;
use App\Models\Employee;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class EmployeeAuthController extends Controller
{
    private const OTP_TTL_SECONDS = 300;

    private const INACTIVE_MESSAGE = 'Your account is not active. Please contact your administrator.';

    private const MAX_VERIFY_ATTEMPTS = 5;

    private const MAX_OTP_REQUESTS_PER_MOBILE = 3;

    private const OTP_REQUEST_WINDOW_SECONDS = 600;

    public function requestOtp(RequestEmployeeOtpRequest $request)
    {
        $mobileNumber = ltrim($request->mobile_number, ' \n\r\t\v\0');
        $requestKey = "otp-request:{$request->country_code}|{$mobileNumber}";

        // Counted per mobile (not per IP) so rotating IPs can't farm fresh codes.
        if (RateLimiter::increment($requestKey, self::OTP_REQUEST_WINDOW_SECONDS) > self::MAX_OTP_REQUESTS_PER_MOBILE) {
            return ApiResponse::error('Too many code requests. Please try again later.', [
                'retry_after' => RateLimiter::availableIn($requestKey),
            ], 429);
        }

        $employees = Employee::where('mobile_country_code', $request->country_code)
            ->where('mobile_number', $mobileNumber)
            ->get();

        if ($employees->isEmpty()) {
            return ApiResponse::error('Employee not found.', null, 404);
        }

        $activeEmployees = $employees->where('status', EmployeeStatus::Active);

        if ($activeEmployees->isEmpty()) {
            return ApiResponse::error(self::INACTIVE_MESSAGE, null, 403);
        }

        // Same number active in two companies: picking one would log into the wrong company.
        if ($activeEmployees->count() > 1) {
            return ApiResponse::error('This number is registered with more than one company. Please contact your administrator.', null, 409);
        }

        $employee = $activeEmployees->first();

        if (! $employee->email) {
            return ApiResponse::error('No email is registered for this account. Please contact your administrator.', null, 422);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $verificationToken = Str::uuid()->toString();

        // The code lives on its own session, so each token has its own attempt budget.
        Cache::put("otp:token:{$verificationToken}", [
            'employee_id' => $employee->id,
            'code' => $code,
        ], self::OTP_TTL_SECONDS);

        // ponytail: OTP sent by email for now, not SMS — swap for an SMS provider before launch
        Mail::to($employee->email)->send(new EmployeeOtpMail($employee, $code));

        return ApiResponse::success('OTP sent successfully.', [
            'verification_token' => $verificationToken,
        ]);
    }

    public function verifyOtp(VerifyEmployeeOtpRequest $request)
    {
        $sessionKey = "otp:token:{$request->verification_token}";
        $session = Cache::get($sessionKey);

        if (! $session) {
            return ApiResponse::error('Invalid or expired OTP session.', null, 422);
        }

        // Atomic increment: concurrent guesses each get a unique count.
        if (RateLimiter::increment("otp-verify:{$request->verification_token}", self::OTP_TTL_SECONDS) > self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget($sessionKey);

            return ApiResponse::error('Too many invalid attempts. Please request a new code.', null, 429);
        }

        if (! hash_equals($session['code'], $request->otp)) {
            return ApiResponse::error('Invalid OTP code.', null, 422);
        }

        // OTP is valid — consume the session immediately
        Cache::forget($sessionKey);

        $employee = Employee::findOrFail($session['employee_id']);

        // Status may have changed during the 5-minute OTP window.
        if ($employee->status !== EmployeeStatus::Active) {
            return ApiResponse::error(self::INACTIVE_MESSAGE, null, 403);
        }

        $device = $request->device;
        // Same value as android_id → same fingerprint, so already-registered Android devices keep working.
        $deviceId = $device['device_id'] ?? $device['android_id'];
        $fingerprint = hash('sha256', $deviceId.$device['brand'].$device['model']);
        $deviceLabel = "{$device['brand']} {$device['model']}";

        if (! $employee->device_fingerprint) {
            // First login — register the device
            $employee->update([
                'device_fingerprint' => $fingerprint,
                'device_name' => $deviceLabel,
            ]);
        } elseif ($employee->device_fingerprint !== $fingerprint) {
            return ApiResponse::error(
                'This account is registered to another device. Please request a device change.',
                null,
                403
            );
        }

        // Revoke previous tokens and issue a fresh one
        $employee->tokens()->delete();
        $token = $employee->createToken($deviceLabel)->plainTextToken;

        return ApiResponse::success('Login successful.', [
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
