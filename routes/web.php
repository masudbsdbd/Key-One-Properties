<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dev\ComponentShowcaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get(config('keyone.login_path'), [LoginController::class, 'show'])->name('login');
    Route::post(config('keyone.login_path'), [LoginController::class, 'store'])->name('login.store');

    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.verify');

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:10,1')->name('password.request.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware(['auth', 'active', 'password.changed', 'two-factor.configured'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');

    Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
        Route::put('password', 'updatePassword')->name('password');
        Route::post('two-factor', 'enableTwoFactor')->name('two-factor.enable');
        Route::post('two-factor/confirm', 'confirmTwoFactor')->name('two-factor.confirm');
        Route::post('two-factor/recovery-codes', 'regenerateRecoveryCodes')->name('two-factor.recovery');
        Route::delete('two-factor', 'disableTwoFactor')->name('two-factor.disable');
        Route::post('sessions/logout-others', 'logoutOtherSessions')->name('sessions.logout-others');
    });

    Route::prefix('attachments')->name('attachments.')->controller(AttachmentController::class)->group(function () {
        Route::get('file/{attachment}', 'show')->name('show');
        Route::delete('file/{attachment}', 'destroy')->name('destroy');
        Route::get('{type}/{owner}', 'index')->name('index');
        Route::post('{type}/{owner}', 'store')->middleware('throttle:30,1')->name('store');
    });

    foreach (['core', 'projects', 'flats', 'customers', 'construction', 'inventory', 'purchasing', 'contractors', 'expenses', 'sales', 'collections', 'investors'] as $module) {
        if (is_file($file = __DIR__."/{$module}.php")) {
            require $file;
        }
    }

    if (app()->isLocal()) {
        Route::prefix('dev/components')->name('dev.components')->group(function () {
            Route::get('/', [ComponentShowcaseController::class, 'index']);
            Route::get('/table', [ComponentShowcaseController::class, 'table'])->name('.table');
            Route::post('/validate', [ComponentShowcaseController::class, 'validateForm'])->name('.validate');
        });
    }
});
