<?php

use App\Modules\Portal\Controllers\Admin\PortalAccessController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal-access')->name('portal-access.')->controller(PortalAccessController::class)->group(function () {
    Route::get('{type}/{owner}', 'panel')->whereIn('type', ['investor', 'customer'])->name('panel');
    Route::get('{type}/{owner}/accounts/create', 'create')->whereIn('type', ['investor', 'customer'])->name('create');
    Route::post('{type}/{owner}/accounts', 'store')->whereIn('type', ['investor', 'customer'])->name('store');
    Route::post('{type}/links/{link}/verify', 'verify')->whereIn('type', ['investor', 'customer'])->name('links.verify');
    Route::get('{type}/links/{link}/revoke', 'revokeForm')->whereIn('type', ['investor', 'customer'])->name('links.revoke.form');
    Route::post('{type}/links/{link}/revoke', 'revoke')->whereIn('type', ['investor', 'customer'])->name('links.revoke');
    Route::get('{type}/links/{link}/password', 'passwordForm')->whereIn('type', ['investor', 'customer'])->name('links.password.form');
    Route::put('{type}/links/{link}/password', 'password')->whereIn('type', ['investor', 'customer'])->name('links.password');
    Route::get('{type}/links/{link}/grants/create', 'grantForm')->whereIn('type', ['investor', 'customer'])->name('grants.create');
    Route::post('{type}/links/{link}/grants', 'grant')->whereIn('type', ['investor', 'customer'])->name('grants.store');
    Route::delete('grants/{grant}', 'revokeGrant')->name('grants.revoke');
});
