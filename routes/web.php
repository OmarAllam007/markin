<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantModuleController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkShiftController;
use App\Http\Controllers\ZkMachineController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->middleware(['auth', 'verified'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('locations', LocationController::class)->except(['show']);
    Route::resource('departments', DepartmentController::class)->except(['show']);
    Route::resource('work-shifts', WorkShiftController::class)->except(['show']);
    Route::get('employees/import', [EmployeeController::class, 'importForm'])->name('employees.import');
    Route::post('employees/import', [EmployeeController::class, 'importStore'])->name('employees.import.store');
    Route::get('employees/import-template', [EmployeeController::class, 'importTemplate'])->name('employees.import.template');
    Route::get('employees/{employee}/attendance', [AttendanceController::class, 'employeeHistory'])->name('employees.attendance.index');
    Route::post('attendances/{attendance}/approve', [AttendanceController::class, 'approve'])->name('attendances.approve');
    Route::post('attendances/{attendance}/lock', [AttendanceController::class, 'lock'])->name('attendances.lock');
    Route::resource('employees', EmployeeController::class)->except(['show']);
    Route::resource('attendances', AttendanceController::class);
    Route::resource('announcements', AnnouncementController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::resource('holidays', HolidayController::class)->except(['show']);
    Route::resource('zk-machines', ZkMachineController::class)->except(['show']);
    Route::post('tenant/switch', [TenantController::class, 'switch'])->name('tenant.switch');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::post('tenant/settings', [TenantSettingsController::class, 'update'])->name('tenant.settings.update');
    Route::post('tenant/modules', [TenantModuleController::class, 'update'])->name('tenant.modules.update');
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('overtime', [ReportController::class, 'overtime'])->name('overtime');
        Route::get('daily-summary', [ReportController::class, 'dailySummary'])->name('daily-summary');
        Route::get('daily-summary/group-detail', [ReportController::class, 'dailySummaryGroupDetail'])->name('daily-summary.group-detail');
        Route::get('monthly-summary', [ReportController::class, 'monthlySummary'])->name('monthly-summary');
        Route::get('detailed-report', [ReportController::class, 'detailedReport'])->name('detailed-report');
        Route::post('detailed-report/manual-attendance', [ReportController::class, 'storeManualAttendance'])->name('detailed-report.manual-attendance');
        Route::get('late-arrivals', [ReportController::class, 'lateArrivals'])->name('late-arrivals');
        Route::get('absence-report', [ReportController::class, 'absenceReport'])->name('absence-report');
        Route::get('missing-punches', [ReportController::class, 'missingPunches'])->name('missing-punches');
        Route::get('department-attendance', [ReportController::class, 'departmentAttendance'])->name('department-attendance');
    });
});
