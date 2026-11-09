<?php

use App\Modules\Reports\Controllers\ExecutiveController;
use App\Modules\Reports\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('executive', [ExecutiveController::class, 'index'])->name('executive.index');
Route::get('executive/widgets/{widget}', [ExecutiveController::class, 'widget'])->middleware('throttle:120,1')->name('executive.widget');

Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{key}', 'show')->name('show');
    Route::get('{key}/table', 'table')->name('table');
    Route::get('{key}/export', 'export')->middleware('throttle:20,1')->name('export');
});
