<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Citizen\DashboardController;
use App\Http\Controllers\Citizen\IncidentController;

// Incluído em routes/api.php dentro do grupo auth:sanctum → URL final: /api/citizen/...
Route::middleware('role:citizen')->prefix('citizen')->name('citizen.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
    Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('/my-incidents', [IncidentController::class, 'myIncidents'])->name('incidents.mine');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
});
