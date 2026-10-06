<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Police\DashboardController;
use App\Http\Controllers\Police\IncidentController;
use App\Http\Controllers\Police\ReportController;

// Incluído em routes/api.php dentro do grupo auth:sanctum → URL final: /api/police/...
Route::middleware('role:police')->prefix('police')->name('police.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::put('/incidents/{incident}/update', [IncidentController::class, 'update'])->name('incidents.update');
    Route::post('/incidents/{incident}/assign', [IncidentController::class, 'assign'])->name('incidents.assign');
    Route::get('/incidents/{incident}/export-pdf', [ReportController::class, 'exportIncidentPdf'])->name('incidents.export-pdf');
});
