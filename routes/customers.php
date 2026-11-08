<?php

use App\Modules\Customers\Controllers\AddressController;
use App\Modules\Customers\Controllers\CustomerController;
use App\Modules\Customers\Controllers\CustomerOptionsController;
use App\Modules\Customers\Controllers\DocumentController;
use App\Modules\Customers\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::get('options/customers', CustomerOptionsController::class)->middleware('throttle:120,1')->name('options.customers');

Route::prefix('customers')->name('customers.')->group(function () {
    Route::controller(CustomerController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('table', 'table')->name('table');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('{customer}/restore', 'restore')->name('restore');
        Route::get('{customer}', 'show')->name('show');
        Route::get('{customer}/edit', 'edit')->name('edit');
        Route::put('{customer}', 'update')->name('update');
        Route::delete('{customer}', 'destroy')->name('destroy');
        Route::post('{customer}/identity', 'identity')->middleware('throttle:20,1')->name('identity');
        Route::get('{customer}/activity', 'activity')->name('activity');
    });

    Route::prefix('{customer}/addresses')->name('addresses.')->controller(AddressController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{address}/edit', 'edit')->name('edit');
        Route::put('{address}', 'update')->name('update');
        Route::delete('{address}', 'destroy')->name('destroy');
    });

    Route::prefix('{customer}/notes')->name('notes.')->controller(NoteController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('{note}', 'destroy')->name('destroy');
    });

    Route::prefix('{customer}/documents')->name('documents.')->controller(DocumentController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:30,1')->name('store');
        Route::get('{document}', 'download')->name('download');
        Route::delete('{document}', 'destroy')->name('destroy');
    });
});
