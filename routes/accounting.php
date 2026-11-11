<?php

use App\Modules\Accounting\Controllers\AccountController;
use App\Modules\Accounting\Controllers\JournalController;
use Illuminate\Support\Facades\Route;

Route::get('options/ledger-accounts', [AccountController::class, 'options'])->middleware('throttle:120,1')->name('options.ledger-accounts');

Route::prefix('accounting')->name('accounting.')->group(function () {
    Route::prefix('accounts')->name('accounts.')->controller(AccountController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('list', 'list')->name('list');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{account}/edit', 'edit')->name('edit');
        Route::put('{account}', 'update')->name('update');
        Route::delete('{account}', 'destroy')->name('destroy');
    });

    Route::prefix('journals')->name('journals.')->controller(JournalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('table', 'table')->name('table');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{journal}', 'show')->name('show');
        Route::get('{journal}/edit', 'edit')->name('edit');
        Route::put('{journal}', 'update')->name('update');
        Route::delete('{journal}', 'destroy')->name('destroy');
        Route::post('{journal}/{action}', 'decide')->whereIn('action', ['submit', 'approve', 'post', 'approve-post'])->name('decide');
        Route::get('{journal}/{action}/form', 'reasonForm')->whereIn('action', ['reject', 'reverse'])->name('reason');
        Route::post('{journal}/{action}/reason', 'reason')->whereIn('action', ['reject', 'reverse'])->name('reason.store');
    });
});
