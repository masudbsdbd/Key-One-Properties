<?php

use App\Http\Controllers\ProfileController;
use App\Modules\Portal\Controllers\Investor\OverviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('investor.dashboard'))->name('home');
Route::get('dashboard', OverviewController::class)->name('dashboard');
Route::get('profile', [ProfileController::class, 'edit'])->name('profile');
