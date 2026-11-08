<?php

use App\Modules\Expenses\Controllers\ExpenseCategoryController;
use App\Modules\Expenses\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::get('options/expense-categories', [ExpenseCategoryController::class, 'options'])->middleware('throttle:120,1')->name('options.expense-categories');
Route::get('options/expense-flats', [ExpenseController::class, 'flatOptions'])->middleware('throttle:120,1')->name('options.expense-flats');

Route::prefix('expense-categories')->name('expense-categories.')->controller(ExpenseCategoryController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{category}/edit', 'edit')->name('edit');
    Route::put('{category}', 'update')->name('update');
});

Route::prefix('expenses')->name('expenses.')->controller(ExpenseController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{expense}', 'show')->name('show');
    Route::get('{expense}/document', 'document')->name('document');
    Route::get('{expense}/edit', 'edit')->name('edit');
    Route::put('{expense}', 'update')->name('update');
    Route::post('{expense}/submit', 'submit')->name('submit');
    Route::post('{expense}/approve', 'approve')->name('approve');
    Route::get('{expense}/action/{action}', 'reasonForm')->whereIn('action', ['reject', 'reverse'])->name('reason');
    Route::post('{expense}/reject', 'reject')->name('reject');
    Route::post('{expense}/reverse', 'reverse')->name('reverse');
    Route::get('{expense}/pay', 'payForm')->name('pay.form');
    Route::post('{expense}/pay', 'pay')->name('pay');
});
