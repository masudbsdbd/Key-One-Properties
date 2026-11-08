<?php

use App\Modules\Core\Controllers\ActivityLogController;
use App\Modules\Core\Controllers\DepartmentController;
use App\Modules\Core\Controllers\OptionsController;
use App\Modules\Core\Controllers\RoleController;
use App\Modules\Core\Controllers\SettingsController;
use App\Modules\Core\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->name('users.')->controller(UserController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{user}/edit', 'edit')->name('edit');
    Route::put('{user}', 'update')->name('update');
    Route::delete('{user}', 'destroy')->name('destroy');
    Route::post('{user}/two-factor/reset', 'resetTwoFactor')->name('two-factor.reset');
});

Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{role}/edit', 'edit')->name('edit');
    Route::put('{role}', 'update')->name('update');
    Route::delete('{role}', 'destroy')->name('destroy');
});

Route::prefix('departments')->name('departments.')->controller(DepartmentController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{department}/edit', 'edit')->name('edit');
    Route::put('{department}', 'update')->name('update');
    Route::delete('{department}', 'destroy')->name('destroy');
});

Route::prefix('options')->name('options.')->middleware('throttle:120,1')->controller(OptionsController::class)->group(function () {
    Route::get('users', 'users')->name('users');
});

Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
Route::get('activity/table', [ActivityLogController::class, 'table'])->name('activity.table');

Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('settings/branding', [SettingsController::class, 'updateBranding'])->name('settings.branding');
