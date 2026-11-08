<?php

use App\Modules\Purchasing\Controllers\PurchaseOrderController;
use App\Modules\Purchasing\Controllers\SupplierBillController;
use App\Modules\Purchasing\Controllers\SupplierController;
use App\Modules\Purchasing\Controllers\SupplierPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('options/suppliers', [SupplierController::class, 'options'])->middleware('throttle:120,1')->name('options.suppliers');

Route::prefix('suppliers')->name('suppliers.')->controller(SupplierController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{supplier}', 'show')->name('show');
    Route::get('{supplier}/edit', 'edit')->name('edit');
    Route::put('{supplier}', 'update')->name('update');
    Route::get('{supplier}/ledger', 'ledger')->name('ledger');
    Route::get('{supplier}/ledger/export', 'exportLedger')->name('ledger.export');
    Route::get('{supplier}/activity', 'activity')->name('activity');
});

Route::prefix('purchase-orders')->name('purchase-orders.')->controller(PurchaseOrderController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{order}', 'show')->name('show');
    Route::get('{order}/edit', 'edit')->name('edit');
    Route::put('{order}', 'update')->name('update');
    Route::post('{order}/submit', 'submit')->name('submit');
    Route::post('{order}/approve', 'approve')->name('approve');
    Route::post('{order}/close', 'close')->name('close');
    Route::get('{order}/action/{action}', 'reasonForm')->whereIn('action', ['reject', 'cancel'])->name('reason');
    Route::post('{order}/reject', 'reject')->name('reject');
    Route::post('{order}/cancel', 'cancel')->name('cancel');
    Route::get('{order}/receive', 'receiveForm')->name('receive.form');
    Route::post('{order}/receive', 'receive')->name('receive');
    Route::get('{order}/receipts/{receipt}/return', 'returnForm')->name('return.form');
    Route::post('{order}/receipts/{receipt}/return', 'returnGoods')->name('return');
});

Route::prefix('supplier-bills')->name('supplier-bills.')->controller(SupplierBillController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::get('billable', 'billable')->name('billable');
    Route::post('/', 'store')->name('store');
    Route::get('{bill}', 'show')->name('show');
    Route::post('{bill}/approve', 'approve')->name('approve');
    Route::post('{bill}/cancel', 'cancel')->name('cancel');
    Route::get('{bill}/reverse', 'reverseForm')->name('reverse.form');
    Route::post('{bill}/reverse', 'reverse')->name('reverse');
});

Route::prefix('supplier-payments')->name('supplier-payments.')->controller(SupplierPaymentController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::get('allocatable', 'allocatable')->name('allocatable');
    Route::post('/', 'store')->name('store');
    Route::get('{payment}', 'show')->name('show');
    Route::post('{payment}/approve', 'approve')->name('approve');
    Route::get('{payment}/action/{action}', 'reasonForm')->whereIn('action', ['reject', 'reverse'])->name('reason');
    Route::post('{payment}/reject', 'reject')->name('reject');
    Route::post('{payment}/reverse', 'reverse')->name('reverse');
});
