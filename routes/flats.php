<?php

use App\Modules\Flats\Controllers\BuildingController;
use App\Modules\Flats\Controllers\BulkFlatController;
use App\Modules\Flats\Controllers\FlatController;
use App\Modules\Flats\Controllers\FlatMediaController;
use App\Modules\Flats\Controllers\FlatOptionsController;
use Illuminate\Support\Facades\Route;

Route::prefix('options')->name('options.')->middleware('throttle:120,1')->controller(FlatOptionsController::class)->group(function () {
    Route::get('buildings', 'buildings')->name('buildings');
    Route::get('floors', 'floors')->name('floors');
});

Route::prefix('units')->name('flats.')->group(function () {
    Route::controller(FlatController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('table', 'table')->name('table');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{flat}', 'show')->name('show');
        Route::get('{flat}/history', 'history')->name('history');
        Route::get('{flat}/edit', 'edit')->name('edit');
        Route::put('{flat}', 'update')->name('update');
        Route::delete('{flat}', 'destroy')->name('destroy');
        Route::get('{flat}/price', 'priceForm')->name('price.edit');
        Route::post('{flat}/price', 'price')->name('price.update');
        Route::get('{flat}/status', 'statusForm')->name('status.edit');
        Route::post('{flat}/status', 'status')->name('status.update');
    });

    Route::prefix('{flat}/media')->name('media.')->controller(FlatMediaController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{media}', 'show')->name('show');
        Route::delete('{media}', 'destroy')->name('destroy');
    });
});

Route::prefix('projects/{project}')->name('projects.')->group(function () {
    Route::prefix('buildings')->name('buildings.')->controller(BuildingController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{building}/edit', 'edit')->name('edit');
        Route::put('{building}', 'update')->name('update');
        Route::delete('{building}', 'destroy')->name('destroy');
        Route::get('{building}/floors', 'floors')->name('floors');
        Route::post('{building}/floors', 'storeFloors')->name('floors.store');
        Route::put('{building}/floors/{floor}', 'updateFloor')->name('floors.update');
        Route::delete('{building}/floors/{floor}', 'destroyFloor')->name('floors.destroy');
    });

    Route::get('units/bulk', [BulkFlatController::class, 'create'])->name('flats.bulk');
    Route::post('units/bulk/preview', [BulkFlatController::class, 'preview'])->name('flats.bulk.preview');
    Route::post('units/bulk', [BulkFlatController::class, 'store'])->name('flats.bulk.store');
});
