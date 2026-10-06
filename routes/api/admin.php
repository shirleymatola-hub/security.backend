<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PoliceStationController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ReportController;

// Incluído em routes/api.php dentro do grupo auth:sanctum → URL final: /api/admin/...
Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Dados dos formulários (antes eram as páginas create/edit do Route::resource).
    // Registados antes do apiResource para "create" não ser lido como {id}.
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::get('/stations/create', [PoliceStationController::class, 'create'])->name('stations.create');
    Route::get('/stations/{station}/edit', [PoliceStationController::class, 'edit'])->name('stations.edit');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');

    // UserController não tem show (no original a rota existia mas o método não).
    Route::apiResource('users', UserController::class)->except(['show']);
    Route::apiResource('stations', PoliceStationController::class);
    Route::apiResource('categories', CategoryController::class);

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');
    Route::get('/reports/export/csv', [ReportController::class, 'exportCsv'])->name('reports.export-csv');
});
