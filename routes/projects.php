<?php

use App\Modules\Projects\Controllers\MediaController;
use App\Modules\Projects\Controllers\MemberController;
use App\Modules\Projects\Controllers\ProgressController;
use App\Modules\Projects\Controllers\ProjectActivityController;
use App\Modules\Projects\Controllers\ProjectController;
use App\Modules\Projects\Controllers\ProjectOptionsController;
use App\Modules\Projects\Controllers\StageController;
use Illuminate\Support\Facades\Route;

Route::get('options/projects', ProjectOptionsController::class)->middleware('throttle:120,1')->name('options.projects');

Route::prefix('projects')->name('projects.')->group(function () {
    Route::controller(ProjectController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('table', 'table')->name('table');
        Route::get('export', 'export')->name('export');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('{project}/restore', 'restore')->name('restore');
        Route::get('{project}', 'show')->name('show');
        Route::get('{project}/summary', 'summary')->name('summary');
        Route::get('{project}/edit', 'edit')->name('edit');
        Route::put('{project}', 'update')->name('update');
        Route::delete('{project}', 'destroy')->name('destroy');
    });

    Route::prefix('{project}/stages')->name('stages.')->controller(StageController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('reorder', 'reorder')->name('reorder');
        Route::get('{stage}/edit', 'edit')->name('edit')->scopeBindings();
        Route::put('{stage}', 'update')->name('update')->scopeBindings();
        Route::delete('{stage}', 'destroy')->name('destroy')->scopeBindings();
    });

    Route::prefix('{project}/progress')->name('progress.')->controller(ProgressController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
    });

    Route::prefix('{project}/media')->name('media.')->controller(MediaController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{media}', 'show')->name('show');
        Route::get('{media}/edit', 'edit')->name('edit');
        Route::put('{media}', 'update')->name('update');
        Route::post('{media}/cover', 'cover')->name('cover');
        Route::delete('{media}', 'destroy')->name('destroy');
    });

    Route::prefix('{project}/members')->name('members.')->controller(MemberController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('{user}/primary', 'primary')->name('primary');
        Route::delete('{user}', 'destroy')->name('destroy');
    });

    Route::get('{project}/activity', ProjectActivityController::class)->name('activity');
});
