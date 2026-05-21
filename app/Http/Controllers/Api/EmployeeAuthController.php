<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RequestEmployeeOtpRequest;
use App\Http\Requests\Api\VerifyEmployeeOtpRequest;
use App\Models\Employee;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EmployeeAuthController extends Controller
{
    public function requestOtp(RequestEmployeeOtpRequest $request)
    {
        $employee = Employee::where('mobile_country_code', $request->country_code)
            ->where('mobile_number', ltrim($request->mobile_number, ' \n\r\t\v\0'))
            ->first();

        if (! $employee) {
            return ApiResponse::error('Employee not found.', null, 404);
        }

        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $verificationToken = Str::uuid()->toString();

        Cache::put("otp:employee:{$employee->id}", $code, 300);
        Cache::put("otp:token:{$verificationToken}", $employee->id, 300);

        // TODO: Send SMS with $code to {$employee->mobile_country_code}{$employee->mobile_number}

        return ApiResponse::success('OTP sent successfully.', [
            'verification_token' => $verificationToken,
            'otp' => $code, // remove once SMS is configured
        ]);
    }

    public function verifyOtp(VerifyEmployeeOtpRequest $request)
    {
        $employeeId = Cache::get("otp:token:{$request->verification_token}");

        if (! $employeeId) {
            return ApiResponse::error('Invalid or expired OTP session.', null, 422);
        }

        $employee = Employee::findOrFail($employeeId);

        $storedCode = Cache::get("otp:employee:{$employee->id}");

        if (! $storedCode || $storedCode !== $request->otp) {
            return ApiResponse::error('Invalid OTP code.', null, 422);
        }

        // OTP is valid — consume both cache entries immediately
        Cache::forget("otp:employee:{$employee->id}");
        Cache::forget("otp:token:{$request->verification_token}");

        $device = $request->device;
        $fingerprint = hash('sha256', $device['android_id'].$device['brand'].$device['model']);
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
