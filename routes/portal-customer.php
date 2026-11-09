<?php

use App\Http\Controllers\ProfileController;
use App\Modules\Portal\Controllers\Customer\OverviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('customer.dashboard'))->name('home');
Route::get('dashboard', OverviewController::class)->name('dashboard');
Route::get('profile', [ProfileController::class, 'edit'])->name('profile');
