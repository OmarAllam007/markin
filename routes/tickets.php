<?php

use App\Http\Controllers\Ticketing\Admin\ApprovalRoleController;
use App\Http\Controllers\Ticketing\Admin\BusinessRuleController;
use App\Http\Controllers\Ticketing\Admin\CategoryController;
use App\Http\Controllers\Ticketing\Admin\GroupController;
use App\Http\Controllers\Ticketing\Admin\PriorityController;
use App\Http\Controllers\Ticketing\Admin\RequestStatusController;
use App\Http\Controllers\Ticketing\Admin\ServiceCatalogController;
use App\Http\Controllers\Ticketing\Admin\SlaController;
use App\Http\Controllers\Ticketing\Admin\SubcategoryController;
use App\Http\Controllers\Ticketing\AttachmentController;
use App\Http\Controllers\Ticketing\TicketApprovalController;
use App\Http\Controllers\Ticketing\TicketController;
use App\Http\Controllers\Ticketing\TicketMessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('ticketing')->name('ticketing.')->group(function () {
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('subcategories', SubcategoryController::class)->except(['show']);
        Route::resource('priorities', PriorityController::class)->except(['show']);
        Route::resource('slas', SlaController::class)->except(['show']);
        Route::resource('groups', GroupController::class)->except(['show']);

        Route::resource('approval-roles', ApprovalRoleController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('business-rules/reorder', [BusinessRuleController::class, 'reorder'])->name('business-rules.reorder');
        Route::resource('business-rules', BusinessRuleController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('request-statuses/reorder', [RequestStatusController::class, 'reorder'])->name('request-statuses.reorder');
        Route::resource('request-statuses', RequestStatusController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('service-catalog', [ServiceCatalogController::class, 'index'])->name('service-catalog.index');
        Route::put('service-catalog/{level}/{id}', [ServiceCatalogController::class, 'update'])
            ->whereIn('level', ['category', 'subcategory', 'item'])->whereNumber('id')->name('service-catalog.update');
        Route::post('service-catalog/subcategories/{subcategory}/items', [ServiceCatalogController::class, 'storeItem'])->name('service-catalog.items.store');
        Route::delete('service-catalog/items/{item}', [ServiceCatalogController::class, 'destroyItem'])->name('service-catalog.items.destroy');
    });

    Route::get('tickets/export', [TicketController::class, 'export'])->name('tickets.export');
    Route::resource('tickets', TicketController::class);
    Route::post('tickets/{ticket}/approvals', [TicketApprovalController::class, 'store'])->name('tickets.approvals.store');
    Route::delete('tickets/{ticket}/approvals/{approval}', [TicketApprovalController::class, 'destroy'])->name('tickets.approvals.destroy');
    Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->name('tickets.messages.store');
    Route::post('tickets/{ticket}/attachments', [AttachmentController::class, 'store'])->name('tickets.attachments.store');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
});
