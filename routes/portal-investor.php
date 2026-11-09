<?php

use App\Http\Controllers\ProfileController;
use App\Modules\Portal\Controllers\Investor\DocumentController;
use App\Modules\Portal\Controllers\Investor\InvestmentController;
use App\Modules\Portal\Controllers\Investor\OverviewController;
use App\Modules\Portal\Controllers\Investor\ProjectController;
use App\Modules\Portal\Controllers\Investor\StatementController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('investor.dashboard'))->name('home');
Route::get('dashboard', OverviewController::class)->name('dashboard');
Route::get('profile', [ProfileController::class, 'edit'])->name('profile');

Route::get('investments', [InvestmentController::class, 'index'])->name('investments.index');
Route::get('investments/table', [InvestmentController::class, 'table'])->name('investments.table');
Route::get('investments/{agreement}', [InvestmentController::class, 'show'])->name('investments.show');

Route::get('statements', [StatementController::class, 'index'])->name('statements.index');
Route::get('statements/table', [StatementController::class, 'table'])->name('statements.table');
Route::get('statements/export', [StatementController::class, 'export'])->middleware('throttle:20,1')->name('statements.export');

Route::prefix('documents')->name('documents.')->controller(DocumentController::class)->middleware('throttle:60,1')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('agreements/{agreement}', 'agreement')->name('agreement');
    Route::get('capital/{transaction}', 'capital')->name('capital');
    Route::get('payouts/{payout}', 'payout')->name('payout');
    Route::get('files/{file}', 'file')->name('file');
});

Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
Route::get('projects/{project}/financials', [ProjectController::class, 'financials'])->name('projects.financials');
Route::get('progress-photos/{file}', [ProjectController::class, 'photo'])->middleware('throttle:120,1')->name('projects.photo');
