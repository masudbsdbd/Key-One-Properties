<?php

use App\Modules\Construction\Controllers\ConstructionController;
use App\Modules\Construction\Controllers\CostController;
use App\Modules\Construction\Controllers\LabourController;
use App\Modules\Construction\Controllers\LabourTypeController;
use App\Modules\Construction\Controllers\MilestoneController;
use App\Modules\Construction\Controllers\ProgressController;
use App\Modules\Construction\Controllers\WorkPackageController;
use Illuminate\Support\Facades\Route;

Route::get('options/work-packages', [WorkPackageController::class, 'options'])->middleware('throttle:120,1')->name('options.work-packages');

Route::prefix('construction')->name('construction.')->group(function () {
    Route::get('/', [ConstructionController::class, 'index'])->name('index');
    Route::get('table', [ConstructionController::class, 'table'])->name('table');
    Route::get('export', [ConstructionController::class, 'export'])->middleware('throttle:20,1')->name('export');
    Route::get('{project}', [ConstructionController::class, 'show'])->name('show');

    Route::prefix('{project}/work-packages')->name('work-packages.')->controller(WorkPackageController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{workPackage}/edit', 'edit')->name('edit');
        Route::put('{workPackage}', 'update')->name('update');
        Route::delete('{workPackage}', 'destroy')->name('destroy');
    });

    Route::prefix('{project}/milestones')->name('milestones.')->controller(MilestoneController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('{milestone}/complete', 'complete')->name('complete');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{milestone}/edit', 'edit')->name('edit');
        Route::put('{milestone}', 'update')->name('update');
        Route::delete('{milestone}', 'destroy')->name('destroy');
    });

    Route::prefix('{project}/progress')->name('progress.')->controller(ProgressController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('{update}/approve', 'approve')->name('approve');
        Route::get('{update}/reject', 'rejectForm')->name('reject.form');
        Route::post('{update}/reject', 'reject')->name('reject');
    });

    Route::get('{project}/cost', [CostController::class, 'table'])->name('cost');
    Route::get('{project}/cost/export', [CostController::class, 'export'])->name('cost.export');
});

Route::prefix('labour/types')->name('labour.types.')->controller(LabourTypeController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('{type}/edit', 'edit')->name('edit');
    Route::put('{type}', 'update')->name('update');
    Route::delete('{type}', 'destroy')->name('destroy');
});

Route::prefix('labour')->name('labour.')->controller(LabourController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('table', 'table')->name('table');
    Route::get('export', 'export')->name('export');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('bulk-approve', 'bulkApprove')->name('bulk-approve');
    Route::post('{entry}/approve', 'approve')->name('approve');
    Route::get('{entry}/{action}', 'reasonForm')->whereIn('action', ['reject', 'reverse'])->name('reason');
    Route::post('{entry}/reject', 'reject')->name('reject');
    Route::post('{entry}/reverse', 'reverse')->name('reverse');
});
