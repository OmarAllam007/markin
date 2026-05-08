<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkShiftController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('locations', LocationController::class)->except(['show']);
    Route::resource('departments', DepartmentController::class)->except(['show']);
    Route::resource('work-shifts', WorkShiftController::class)->except(['show']);
    Route::get('employees/import', [EmployeeController::class, 'importForm'])->name('employees.import');
    Route::post('employees/import', [EmployeeController::class, 'importStore'])->name('employees.import.store');
    Route::get('employees/import-template', [EmployeeController::class, 'importTemplate'])->name('employees.import.template');
    Route::resource('employees', EmployeeController::class)->except(['show']);
    Route::resource('announcements', AnnouncementController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('tenant/switch', [TenantController::class, 'switch'])->name('tenant.switch');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::post('tenant/settings', [TenantSettingsController::class, 'update'])->name('tenant.settings.update');
});
