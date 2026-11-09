<?php

use App\Http\Controllers\ProfileController;
use App\Modules\Portal\Controllers\Customer\InstallmentController;
use App\Modules\Portal\Controllers\Customer\OverviewController;
use App\Modules\Portal\Controllers\Customer\PaymentController;
use App\Modules\Portal\Controllers\Customer\StatementController;
use App\Modules\Portal\Controllers\Customer\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('customer.dashboard'))->name('home');
Route::get('dashboard', OverviewController::class)->name('dashboard');
Route::get('profile', [ProfileController::class, 'edit'])->name('profile');

Route::get('units', [UnitController::class, 'index'])->name('flats.index');
Route::get('units/{unit}', [UnitController::class, 'show'])->name('flats.show');
Route::get('installments', [InstallmentController::class, 'index'])->name('installments.index');
Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
Route::get('payments/table', [PaymentController::class, 'table'])->name('payments.table');
Route::get('receipts/{payment}', [PaymentController::class, 'receipt'])->middleware('throttle:60,1')->name('receipts.show');
Route::get('statement', [StatementController::class, 'index'])->name('statements.index');
Route::get('statement/table', [StatementController::class, 'table'])->name('statements.table');
Route::get('statement/export', [StatementController::class, 'export'])->middleware('throttle:20,1')->name('statements.export');
