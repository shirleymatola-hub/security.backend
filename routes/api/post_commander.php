<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostCommander\DashboardController;
use App\Http\Controllers\PostCommander\IncidentController;
use App\Http\Controllers\PostCommander\ReportController;

// Incluído em routes/api.php dentro do grupo auth:sanctum → URL final: /api/post-commander/...
Route::middleware('role:post_commander')->prefix('post-commander')->name('post_commander.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::put('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::post('/incidents/{incident}/assign', [IncidentController::class, 'assign'])->name('incidents.assign');
    Route::get('/station-incidents', [IncidentController::class, 'stationIncidents'])->name('station.incidents');

    Route::get('/agents', [IncidentController::class, 'agents'])->name('agents');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');
});
