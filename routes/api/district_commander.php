<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DistrictCommander\DashboardController;
use App\Http\Controllers\DistrictCommander\IncidentController;
use App\Http\Controllers\DistrictCommander\ReportController;
use App\Http\Controllers\DistrictCommander\StationController;

// Incluído em routes/api.php dentro do grupo auth:sanctum → URL final: /api/district-commander/...
Route::middleware('role:district_commander')->prefix('district-commander')->name('district_commander.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::put('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::post('/incidents/{incident}/assign', [IncidentController::class, 'assign'])->name('incidents.assign');

    Route::get('/stations', [IncidentController::class, 'stations'])->name('stations');
    Route::get('/stations/{station}', [StationController::class, 'show'])->name('stations.show');
    Route::get('/incidents-json', [IncidentController::class, 'incidentsJson'])->name('incidents.json');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');
});
