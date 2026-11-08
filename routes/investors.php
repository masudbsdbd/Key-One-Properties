<?php

use App\Modules\Investors\Controllers\AgreementController;
use App\Modules\Investors\Controllers\CapitalController;
use App\Modules\Investors\Controllers\InvestorController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->group(function () {
    Route::get('options/investors', [InvestorController::class, 'options'])->name('options.investors');
    Route::get('options/investment-flats', [AgreementController::class, 'flatOptions'])->name('options.investment-flats');
    Route::get('options/investor-agreements', [AgreementController::class, 'agreementOptions'])->name('options.investor-agreements');
    Route::get('investor-agreements/shares', [AgreementController::class, 'shares'])->name('investor-agreements.shares');
});

Route::prefix('investors')->name('investors.')->controller(InvestorController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{investor}', 'show')->name('show');
    Route::get('{investor}/edit', 'edit')->name('edit');
    Route::put('{investor}', 'update')->name('update');
    Route::get('{investor}/ledger', 'ledger')->name('ledger');
    Route::get('{investor}/ledger/export', 'ledgerExport')->name('ledger.export');
});

Route::prefix('investor-agreements')->name('investor-agreements.')->controller(AgreementController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{agreement}', 'show')->name('show');
    Route::get('{agreement}/edit', 'edit')->name('edit');
    Route::put('{agreement}', 'update')->name('update');
    Route::get('{agreement}/document', 'document')->name('document');
    Route::post('{agreement}/{action}', 'decide')->whereIn('action', ['submit', 'approve', 'amend'])->name('decide');
    Route::get('{agreement}/{action}/form', 'reasonForm')->whereIn('action', ['reject', 'cancel', 'close'])->name('reason');
    Route::post('{agreement}/{action}/reason', 'reason')->whereIn('action', ['reject', 'cancel', 'close'])->name('reason.store');
});

Route::prefix('investor-capital')->name('investor-capital.')->controller(CapitalController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{transaction}', 'show')->name('show');
    Route::get('{transaction}/document', 'document')->name('document');
    Route::post('{transaction}/post', 'post')->name('post');
    Route::get('{transaction}/{action}/form', 'reasonForm')->whereIn('action', ['reject', 'reverse'])->name('reason');
    Route::post('{transaction}/{action}/reason', 'reason')->whereIn('action', ['reject', 'reverse'])->name('reason.store');
});
