<?php

use App\Modules\Contractors\Controllers\ContractorBillController;
use App\Modules\Contractors\Controllers\ContractorController;
use App\Modules\Contractors\Controllers\ContractorPaymentController;
use App\Modules\Contractors\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->group(function () {
    Route::get('options/contractors', [ContractorController::class, 'options'])->name('options.contractors');
    Route::get('options/work-orders', [WorkOrderController::class, 'options'])->name('options.work-orders');
    Route::get('work-orders/payable', [WorkOrderController::class, 'payable'])->name('work-orders.payable');
});

Route::prefix('contractors')->name('contractors.')->controller(ContractorController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{contractor}', 'show')->name('show');
    Route::get('{contractor}/edit', 'edit')->name('edit');
    Route::put('{contractor}', 'update')->name('update');
    Route::get('{contractor}/ledger', 'ledger')->name('ledger');
    Route::get('{contractor}/ledger/export', 'exportLedger')->name('ledger.export');
    Route::get('{contractor}/activity', 'activity')->name('activity');
});

Route::prefix('work-orders')->name('work-orders.')->group(function () {
    Route::controller(WorkOrderController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('table', 'table')->name('table');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('export', 'export')->name('export');
        Route::get('{order}', 'show')->name('show');
        Route::get('{order}/document', 'document')->name('document');
        Route::get('{order}/edit', 'edit')->name('edit');
        Route::put('{order}', 'update')->name('update');
        Route::post('{order}/approve', 'approve')->name('approve');
        Route::get('{order}/cancel', 'cancelForm')->name('cancel.form');
        Route::post('{order}/cancel', 'cancel')->name('cancel');
        Route::post('{order}/settle', 'settle')->name('settle');
        Route::get('{order}/measurements/create', 'measurementForm')->name('measurements.create');
        Route::post('{order}/measurements', 'storeMeasurement')->name('measurements.store');
        Route::post('{order}/measurements/{measurement}/approve', 'approveMeasurement')->name('measurements.approve');
        Route::get('{order}/measurements/{measurement}/reject', 'rejectMeasurementForm')->name('measurements.reject.form');
        Route::post('{order}/measurements/{measurement}/reject', 'rejectMeasurement')->name('measurements.reject');
    });
    Route::get('{order}/bills/create', [ContractorBillController::class, 'create'])->name('bills.create');
    Route::post('{order}/bills', [ContractorBillController::class, 'store'])->name('bills.store');
});

Route::prefix('contractor-bills')->name('contractor-bills.')->controller(ContractorBillController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('{bill}', 'show')->name('show');
    Route::get('{bill}/document', 'document')->name('document');
    Route::post('{bill}/approve', 'approve')->name('approve');
    Route::post('{bill}/cancel', 'cancel')->name('cancel');
    Route::get('{bill}/reverse', 'reverseForm')->name('reverse.form');
    Route::post('{bill}/reverse', 'reverse')->name('reverse');
});

Route::prefix('contractor-payments')->name('contractor-payments.')->controller(ContractorPaymentController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{payment}', 'show')->name('show');
    Route::get('{payment}/document', 'document')->name('document');
    Route::post('{payment}/approve', 'approve')->name('approve');
    Route::get('{payment}/action/{action}', 'reasonForm')->whereIn('action', ['reject', 'reverse'])->name('reason');
    Route::post('{payment}/reject', 'reject')->name('reject');
    Route::post('{payment}/reverse', 'reverse')->name('reverse');
});
