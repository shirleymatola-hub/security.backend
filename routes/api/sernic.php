<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Sernic\DashboardController;
use App\Http\Controllers\Sernic\IncidentController;
use App\Http\Controllers\Sernic\ReportController;

// Incluído em routes/api.php dentro do grupo auth:sanctum → URL final: /api/sernic/...
Route::middleware('role:sernic_officer')->prefix('sernic')->name('sernic_officer.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');
});
