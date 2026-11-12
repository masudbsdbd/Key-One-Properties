<?php

use App\Modules\Accounting\Controllers\AccountController;
use App\Modules\Accounting\Controllers\BankReconciliationController;
use App\Modules\Accounting\Controllers\CashBankController;
use App\Modules\Accounting\Controllers\ChequeController;
use App\Modules\Accounting\Controllers\JournalController;
use App\Modules\Accounting\Controllers\PeriodController;
use App\Modules\Accounting\Controllers\ReconciliationController;
use App\Modules\Accounting\Controllers\ReportController;
use App\Modules\Accounting\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('options/money-accounts', [CashBankController::class, 'options'])->middleware('throttle:120,1')->name('options.money-accounts');
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

    Route::controller(ReportController::class)->group(function () {
        Route::get('/', 'overview')->name('overview');
        Route::get('ledger', 'ledger')->name('ledger');
        Route::get('trial-balance', 'trialBalance')->name('trial-balance');
        Route::get('profit-loss', 'profitLoss')->name('profit-loss');
        Route::get('balance-sheet', 'balanceSheet')->name('balance-sheet');
        Route::get('cash-flow', 'cashFlow')->name('cash-flow');
        Route::get('project-financials', 'projects')->name('projects');
        Route::get('receivables', 'receivables')->name('receivables');
        Route::get('payables', 'payables')->name('payables');
    });

    Route::prefix('periods')->name('periods.')->controller(PeriodController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('list', 'list')->name('list');
        Route::get('reason/{type}/{id}', 'reasonForm')->whereIn('type', ['period', 'year'])->name('reason');
        Route::post('months/{period}/lock', 'lock')->name('lock');
        Route::post('months/{period}/unlock', 'unlock')->name('unlock');
        Route::post('years/{year}/close', 'close')->name('close');
        Route::post('years/{year}/reopen', 'reopen')->name('reopen');
    });

    Route::prefix('reconciliation')->name('reconciliation.')->controller(ReconciliationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('checks', 'checks')->name('checks');
        Route::get('table', 'table')->name('table');
        Route::post('post-all', 'postAll')->name('post-all');
        Route::post('events/{event}/retry', 'retry')->name('retry');
        Route::get('events/{event}/account', 'accountForm')->name('account');
        Route::post('events/{event}/account', 'setAccount')->name('account.store');
    });

    Route::controller(SettingsController::class)->group(function () {
        Route::get('settings', 'edit')->name('settings.edit');
        Route::put('settings', 'update')->name('settings.update');
        Route::put('settings/defaults', 'defaults')->name('settings.defaults');
        Route::get('settings/mapping', 'mappingForm')->name('settings.mapping.form');
        Route::put('settings/mapping', 'mapping')->name('settings.mapping');
        Route::get('opening-balances', 'opening')->name('opening.edit');
        Route::put('opening-balances', 'saveOpening')->name('opening.update');
    });

    Route::prefix('cash-bank')->name('cash-bank.')->controller(CashBankController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('accounts', 'accounts')->name('accounts');
        Route::get('table', 'table')->name('table');
        Route::get('export', 'export')->middleware('throttle:20,1')->name('export');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('move', 'moveForm')->name('move');
        Route::post('move', 'move')->name('move.store');
        Route::get('transactions/{transaction}/cancel', 'cancelForm')->name('cancel');
        Route::post('transactions/{transaction}/cancel', 'cancel')->name('cancel.store');
        Route::get('{account}', 'show')->name('show');
        Route::get('{account}/balance', 'balance')->name('balance');
        Route::get('{account}/edit', 'edit')->name('edit');
        Route::put('{account}', 'update')->name('update');
        Route::delete('{account}', 'destroy')->name('destroy');
    });

    Route::name('bank-reconciliation.')->controller(BankReconciliationController::class)->group(function () {
        Route::get('cash-bank/{account}/reconcile', 'index')->name('index');
        Route::post('cash-bank/{account}/reconcile', 'store')->name('store');
        Route::get('bank-reconciliations/{reconciliation}', 'show')->name('show');
        Route::post('bank-reconciliations/{reconciliation}/tick', 'tick')->name('tick');
        Route::post('bank-reconciliations/{reconciliation}/complete', 'complete')->name('complete');
        Route::delete('bank-reconciliations/{reconciliation}', 'destroy')->name('destroy');
    });

    Route::prefix('cheques')->name('cheques.')->controller(ChequeController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('table', 'table')->name('table');
        Route::get('{cheque}/{action}', 'form')->whereIn('action', ['deposit', 'clear', 'bounce'])->name('form');
        Route::post('{cheque}/{action}', 'update')->whereIn('action', ['deposit', 'clear', 'bounce'])->name('update');
    });

    Route::prefix('journals')->name('journals.')->controller(JournalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('table', 'table')->name('table');
        Route::get('export', 'export')->middleware('throttle:20,1')->name('export');
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
