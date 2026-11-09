<?php

use App\Modules\Sales\Controllers\BookingController;
use App\Modules\Sales\Controllers\CancellationController;
use App\Modules\Sales\Controllers\PriceRevisionController;
use App\Modules\Sales\Controllers\RefundController;
use App\Modules\Sales\Controllers\SaleController;
use App\Modules\Sales\Controllers\SalesOverviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->group(function () {
    Route::get('options/sale-flats', [BookingController::class, 'flatOptions'])->name('options.sale-flats');
    Route::get('sales-desk/flat-summary', [BookingController::class, 'flatSummary'])->name('sales.flat-summary');
});

Route::get('sales-desk', [SalesOverviewController::class, 'overview'])->name('sales.overview');
Route::get('sales-desk/summary', [SalesOverviewController::class, 'summaryExport'])->name('sales.summary');
Route::get('sales-desk/availability', [SalesOverviewController::class, 'availability'])->name('sales.availability');
Route::get('sales-desk/availability/board', [SalesOverviewController::class, 'board'])->name('sales.board');

Route::prefix('bookings')->name('bookings.')->controller(BookingController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{booking}', 'show')->name('show');
    Route::get('{booking}/edit', 'edit')->name('edit');
    Route::put('{booking}', 'update')->name('update');
    Route::post('{booking}/submit', 'submit')->name('submit');
    Route::post('{booking}/approve', 'approve')->name('approve');
    Route::get('{booking}/action/{action}', 'reasonForm')->whereIn('action', ['reject', 'withdraw'])->name('reason');
    Route::post('{booking}/reject', 'reject')->name('reject');
    Route::post('{booking}/withdraw', 'withdraw')->name('withdraw');
    Route::get('{booking}/cancel', 'cancelForm')->name('cancel.form');
    Route::post('{booking}/cancel', 'requestCancel')->name('cancel');
});

Route::prefix('sales')->name('sales.')->controller(SaleController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{sale}', 'show')->name('show');
    Route::get('{sale}/edit', 'edit')->name('edit');
    Route::put('{sale}', 'update')->name('update');
    Route::post('{sale}/submit', 'submit')->name('submit');
    Route::post('{sale}/approve', 'approve')->name('approve');
    Route::get('{sale}/reject', 'rejectForm')->name('reject.form');
    Route::post('{sale}/reject', 'reject')->name('reject');
    Route::get('{sale}/stage/{stage}', 'stageForm')->whereIn('stage', ['agreement', 'registration', 'handover'])->name('stage.form');
    Route::post('{sale}/stage/{stage}', 'updateStage')->whereIn('stage', ['agreement', 'registration', 'handover'])->name('stage.update');
    Route::get('{sale}/cancel', 'cancelForm')->name('cancel.form');
    Route::post('{sale}/cancel', 'requestCancel')->name('cancel');
    Route::get('{sale}/document', 'document')->name('document');
});

Route::prefix('cancellations')->name('cancellations.')->controller(CancellationController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->middleware('throttle:20,1')->name('export');
    Route::post('{cancellation}/approve', 'approve')->name('approve');
    Route::get('{cancellation}/reject', 'rejectForm')->name('reject.form');
    Route::post('{cancellation}/reject', 'reject')->name('reject');
});

Route::prefix('refunds')->name('refunds.')->controller(RefundController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{refund}', 'show')->name('show');
    Route::post('{refund}/approve', 'approve')->name('approve');
    Route::get('{refund}/approve', 'approveForm')->name('approve.form');
    Route::get('{refund}/action/{action}', 'reasonForm')->whereIn('action', ['reject', 'reverse'])->name('reason');
    Route::post('{refund}/reject', 'reject')->name('reject');
    Route::get('{refund}/pay', 'payForm')->name('pay.form');
    Route::post('{refund}/pay', 'pay')->name('pay');
    Route::post('{refund}/reverse', 'reverse')->name('reverse');
    Route::get('{refund}/document', 'document')->name('document');
});

Route::prefix('price-revisions')->name('price-revisions.')->controller(PriceRevisionController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::post('{revision}/approve', 'approve')->name('approve');
    Route::get('{revision}/reject', 'rejectForm')->name('reject.form');
    Route::post('{revision}/reject', 'reject')->name('reject');
});
