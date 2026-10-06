<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Manager\DashboardController;
use App\Http\Controllers\Manager\IncidentController;
use App\Http\Controllers\Manager\ReportController;

// Incluído em routes/api.php dentro do grupo auth:sanctum → URL final: /api/manager/...
Route::middleware('role:manager')->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    // Rota "incidents/{incident}/edit" removida: usava uma view inexistente (manager.incidents.edit).
    Route::put('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::post('/incidents/{incident}/assign', [IncidentController::class, 'assign'])->name('incidents.assign');
    Route::post('/incidents/{incident}/forward-sernic', [IncidentController::class, 'forwardToSernic'])->name('incidents.forward-sernic');
    Route::post('/incidents/{incident}/receive', [IncidentController::class, 'receive'])->name('incidents.receive');
    Route::post('/incidents/{incident}/mark-viewed', [IncidentController::class, 'markViewed'])->name('incidents.mark-viewed');

    Route::get('/station-incidents', [IncidentController::class, 'stationIncidents'])->name('station.incidents');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');
    Route::get('/reports/export/service-order', [ReportController::class, 'exportServiceOrder'])->name('reports.export-service-order');
});
