<?php

use App\Modules\Core\Controllers\RoleController;
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
