<?php

use App\Modules\Collections\Controllers\CollectionsController;
use App\Modules\Collections\Controllers\CustomerPaymentController;
use App\Modules\Collections\Controllers\InstallmentPlanController;
use App\Modules\Collections\Controllers\LateFeeController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->group(function () {
    Route::get('options/customer-sales', [LateFeeController::class, 'saleOptions'])->name('options.customer-sales');
    Route::get('options/plan-sales', [InstallmentPlanController::class, 'saleOptions'])->name('options.plan-sales');
});

Route::get('collections', [CollectionsController::class, 'overview'])->name('collections.overview');
Route::prefix('dues')->name('dues.')->controller(CollectionsController::class)->group(function () {
    Route::get('/', 'dues')->name('index');
    Route::get('table', 'duesTable')->name('table');
    Route::get('export', 'duesExport')->name('export');
});
Route::prefix('customers/{customer}/statement')->name('customers.statement')->controller(CollectionsController::class)->group(function () {
    Route::get('/', 'statement');
    Route::get('table', 'statementTable')->name('.table');
    Route::get('export', 'statementExport')->name('.export');
});

Route::prefix('installment-plans')->name('installment-plans.')->controller(InstallmentPlanController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('preview', 'preview')->name('preview');
    Route::post('/', 'store')->name('store');
    Route::get('{plan}', 'show')->name('show');
    Route::get('{plan}/document', 'document')->name('document');
    Route::post('{plan}/{action}', 'decide')->whereIn('action', ['submit', 'approve', 'reject', 'discard'])->name('decide');
    Route::post('{plan}/late-fees', 'assessFees')->name('late-fees');
    Route::get('{plan}/reschedule', 'rescheduleForm')->name('reschedule.form');
    Route::post('{plan}/reschedule', 'requestReschedule')->name('reschedule');
    Route::get('{plan}/reschedules/{reschedule}/reject', 'rescheduleRejectForm')->name('reschedules.reject.form');
    Route::post('{plan}/reschedules/{reschedule}/{action}', 'decideReschedule')->whereIn('action', ['approve', 'reject'])->name('reschedules.decide');
});

Route::prefix('customer-payments')->name('customer-payments.')->controller(CustomerPaymentController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::get('preview', 'preview')->name('preview');
    Route::post('/', 'store')->name('store');
    Route::get('{payment}', 'show')->name('show');
    Route::get('{payment}/receipt', 'receipt')->name('receipt');
    Route::post('{payment}/post', 'post')->name('post');
    Route::get('{payment}/action/{action}', 'reasonForm')->whereIn('action', ['reject', 'reverse'])->name('reason');
    Route::post('{payment}/reject', 'reject')->name('reject');
    Route::post('{payment}/reverse', 'reverse')->name('reverse');
});

Route::prefix('late-fees')->name('late-fees.')->controller(LateFeeController::class)->group(function () {
    Route::get('export', 'export')->middleware('throttle:20,1')->name('export');
    Route::get('/', 'index')->name('index');
    Route::get('policies', 'policies')->name('policies');
    Route::get('assessments', 'assessments')->name('assessments');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{assessment}/waive', 'waiveForm')->name('waive.form');
    Route::post('{assessment}/waive', 'waive')->name('waive');
});
