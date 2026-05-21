<?php

use App\Http\Controllers\Api\AttendancePunchController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\LocationCheckController;
use App\Http\Controllers\ZkPushController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('employee/attendance/punch', [AttendancePunchController::class, 'store'])->name('api.employee.attendance.punch');
    Route::get('employee/location/check', [LocationCheckController::class, 'check'])->name('api.employee.location.check');
});

Route::prefix('employee/auth')->name('api.employee.auth.')->middleware('throttle:5,1')->group(function () {
    Route::post('request-otp', [EmployeeAuthController::class, 'requestOtp'])->name('request-otp');
    Route::post('verify-otp', [EmployeeAuthController::class, 'verifyOtp'])->name('verify-otp');
});

// ZKTeco biometric machine PUSH protocol — paths are firmware-defined and cannot be changed
Route::prefix('iclock')->name('zk.')->middleware('throttle:120,1')->group(function () {
    Route::get('cdata', [ZkPushController::class, 'handshake'])->name('handshake');
    Route::post('cdata', [ZkPushController::class, 'push'])->name('push');
    Route::get('getrequest', [ZkPushController::class, 'getRequest'])->name('getrequest');
    Route::post('devicecmd', [ZkPushController::class, 'deviceCmd'])->name('devicecmd');
});
