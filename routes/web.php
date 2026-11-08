<?php

use App\Http\Controllers\Dev\ComponentShowcaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

if (app()->isLocal()) {
    Route::prefix('dev/components')->name('dev.components')->group(function () {
        Route::get('/', [ComponentShowcaseController::class, 'index']);
        Route::get('/table', [ComponentShowcaseController::class, 'table'])->name('.table');
        Route::post('/validate', [ComponentShowcaseController::class, 'validateForm'])->name('.validate');
    });
}
