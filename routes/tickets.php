<?php

use App\Http\Controllers\Ticketing\Admin\CategoryController;
use App\Http\Controllers\Ticketing\Admin\FormFieldController;
use App\Http\Controllers\Ticketing\Admin\GroupController;
use App\Http\Controllers\Ticketing\Admin\PriorityController;
use App\Http\Controllers\Ticketing\Admin\SlaController;
use App\Http\Controllers\Ticketing\Admin\SubcategoryController;
use App\Http\Controllers\Ticketing\AttachmentController;
use App\Http\Controllers\Ticketing\TicketApprovalController;
use App\Http\Controllers\Ticketing\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('ticketing')->name('ticketing.')->group(function () {
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('subcategories', SubcategoryController::class)->except(['show']);
        Route::resource('priorities', PriorityController::class)->except(['show']);
        Route::resource('slas', SlaController::class)->except(['show']);
        Route::resource('groups', GroupController::class)->except(['show']);

        Route::get('form-fields', [FormFieldController::class, 'index'])->name('form-fields.index');
        Route::get('form-fields/{type}', [FormFieldController::class, 'show'])->name('form-fields.show');
        Route::post('form-fields/{type}', [FormFieldController::class, 'store'])->name('form-fields.store');
        Route::delete('form-fields/{type}/{field}', [FormFieldController::class, 'destroy'])->name('form-fields.destroy');
    });

    Route::resource('tickets', TicketController::class);
    Route::post('tickets/{ticket}/approve', [TicketApprovalController::class, 'store'])->name('tickets.approve');
    Route::post('tickets/{ticket}/attachments', [AttachmentController::class, 'store'])->name('tickets.attachments.store');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
});
