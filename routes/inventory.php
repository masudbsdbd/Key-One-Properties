<?php

use App\Modules\Inventory\Controllers\AdjustmentController;
use App\Modules\Inventory\Controllers\BorrowController;
use App\Modules\Inventory\Controllers\MaterialController;
use App\Modules\Inventory\Controllers\StockController;
use App\Modules\Inventory\Controllers\TransferController;
use App\Modules\Inventory\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->group(function () {
    Route::get('options/materials', [MaterialController::class, 'options'])->name('options.materials');
    Route::get('options/warehouses', [WarehouseController::class, 'options'])->name('options.warehouses');
    Route::get('stock/available', [StockController::class, 'available'])->name('stock.available');
});

Route::prefix('materials')->name('materials.')->controller(MaterialController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('catalog', 'catalog')->name('catalog');
    Route::post('categories', 'storeCategory')->name('categories.store');
    Route::post('units', 'storeUnit')->name('units.store');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{material}/edit', 'edit')->name('edit');
    Route::put('{material}', 'update')->name('update');
    Route::delete('{material}', 'destroy')->name('destroy');
});

Route::prefix('warehouses')->name('warehouses.')->controller(WarehouseController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{warehouse}/edit', 'edit')->name('edit');
    Route::put('{warehouse}', 'update')->name('update');
});

Route::prefix('stock')->name('stock.')->controller(StockController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'exportBalances')->name('export');
    Route::get('receive', 'receiveForm')->name('receive.form');
    Route::post('receive', 'receive')->name('receive');
    Route::get('issue', 'issueForm')->name('issue.form');
    Route::post('issue', 'issue')->name('issue');
    Route::get('movements', 'movements')->name('movements');
    Route::get('movements/table', 'movementsTable')->name('movements.table');
    Route::get('movements/export', 'export')->name('movements.export');
    Route::get('issues/{issue}/reverse', 'reverseIssueForm')->name('issues.reverse.form');
    Route::post('issues/{issue}/reverse', 'reverseIssue')->name('issues.reverse');
});

Route::prefix('adjustments')->name('adjustments.')->controller(AdjustmentController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('{adjustment}/approve', 'approve')->name('approve');
    Route::get('{adjustment}/reject', 'rejectForm')->name('reject.form');
    Route::post('{adjustment}/reject', 'reject')->name('reject');
});

Route::prefix('borrows')->name('borrows.')->controller(BorrowController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{borrow}', 'show')->name('show');
    Route::get('{borrow}/document', 'document')->name('document');
    Route::get('{borrow}/approve', 'approveForm')->name('approve.form');
    Route::post('{borrow}/approve', 'approve')->name('approve');
    Route::post('{borrow}/issue', 'issue')->name('issue');
    Route::get('{borrow}/{mode}', 'settleForm')->whereIn('mode', ['return', 'convert'])->name('settle.form');
    Route::post('{borrow}/{mode}', 'settle')->whereIn('mode', ['return', 'convert'])->name('settle');
    Route::get('{borrow}/action/{action}', 'reasonForm')->whereIn('action', ['reject', 'cancel'])->name('reason');
    Route::post('{borrow}/reject', 'reject')->name('reject');
    Route::post('{borrow}/cancel', 'cancel')->name('cancel');
});

Route::prefix('transfers')->name('transfers.')->controller(TransferController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('create', 'create')->name('create');
    Route::post('preview', 'preview')->name('preview');
    Route::post('/', 'store')->name('store');
    Route::get('{transfer}', 'show')->name('show');
    Route::get('{transfer}/document', 'document')->name('document');
    Route::post('{transfer}/approve', 'approve')->name('approve');
    Route::post('{transfer}/post', 'post')->name('post');
    Route::get('{transfer}/action/{action}', 'reasonForm')->whereIn('action', ['reject', 'cancel', 'reverse'])->name('reason');
    Route::post('{transfer}/reject', 'reject')->name('reject');
    Route::post('{transfer}/cancel', 'cancel')->name('cancel');
    Route::post('{transfer}/reverse', 'reverse')->name('reverse');
});
