<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SquadCommander\DashboardController;
use App\Http\Controllers\SquadCommander\IncidentController;
use App\Http\Controllers\SquadCommander\ReportController;

// Incluído em routes/api.php dentro do grupo auth:sanctum → URL final: /api/squad-commander/...
Route::middleware('role:squad_commander')->prefix('squad-commander')->name('squad_commander.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::put('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::post('/incidents/{incident}/assign', [IncidentController::class, 'assign'])->name('incidents.assign');

    Route::get('/stations', [IncidentController::class, 'stations'])->name('stations');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');
    Route::get('/reports/station-comparison', [ReportController::class, 'stationComparison'])->name('reports.station-comparison');
});
