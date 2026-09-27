<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AttendancePunchController;
use App\Http\Controllers\Api\AttendanceReportController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeMeController;
use App\Http\Controllers\Api\EmployeeNotificationController;
use App\Http\Controllers\Api\LocationCheckController;
use App\Http\Controllers\Api\Ticketing\TicketCategoryController;
use App\Http\Controllers\Api\Ticketing\TicketCategoryFormController;
use App\Http\Controllers\ZkPushController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('employee/me', EmployeeMeController::class)->name('api.employee.me');
    Route::post('employee/attendance/punch', [AttendancePunchController::class, 'store'])->name('api.employee.attendance.punch');
    Route::get('employee/location/check', [LocationCheckController::class, 'check'])->name('api.employee.location.check');

    Route::prefix('employee/attendance')->name('api.employee.attendance.')->group(function () {
        Route::get('/', [AttendanceReportController::class, 'index'])->name('index');
        Route::get('/missing', [AttendanceReportController::class, 'missing'])->name('missing');
    });

    Route::prefix('employee/notifications')->name('api.employee.notifications.')->group(function () {
        Route::get('/', [EmployeeNotificationController::class, 'index'])->name('index');
        Route::post('/read-all', [EmployeeNotificationController::class, 'markAllRead'])->name('read-all');
        Route::patch('/{notification}/read', [EmployeeNotificationController::class, 'markRead'])->name('read');
    });

    Route::prefix('employee/announcements')->name('api.employee.announcements.')->group(function () {
        Route::get('/', [AnnouncementController::class, 'index'])->name('index');
        Route::post('/read-all', [AnnouncementController::class, 'markAllRead'])->name('read-all');
        Route::patch('/{announcement}/read', [AnnouncementController::class, 'markRead'])->name('read');
    });

    Route::prefix('employee/tickets')->name('api.employee.tickets.')->middleware('api.employee.module:ticketing')->group(function () {
        Route::get('categories', [TicketCategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/{category}', [TicketCategoryController::class, 'show'])->name('categories.show');
        Route::get('categories/{category}/form', TicketCategoryFormController::class)->name('categories.form');
    });
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
