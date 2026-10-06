<?php

use App\Http\Controllers\Internal\HealthController;
use App\Http\Controllers\Internal\UserController;
use App\Http\Controllers\Internal\InvitationController;
use App\Http\Controllers\Internal\PoliceStationController;
use App\Http\Controllers\Internal\DistrictController;
use App\Http\Controllers\Internal\NeighborhoodController;
use App\Http\Controllers\Internal\IncidentController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')->name('internal.')->middleware(['api', 'internal_api'])->group(function () {

    Route::get('/health', HealthController::class)
        ->name('health')
        ->middleware('internal_api:');

    Route::get('/users', [UserController::class, 'index'])
        ->name('users.index')
        ->middleware('internal_api:users:read');

    Route::get('/users/{id}', [UserController::class, 'show'])
        ->name('users.show')
        ->middleware('internal_api:users:read');

    Route::get('/invitations', [InvitationController::class, 'index'])
        ->name('invitations.index')
        ->middleware('internal_api:invitations:read');

    Route::get('/invitations/{id}', [InvitationController::class, 'show'])
        ->name('invitations.show')
        ->middleware('internal_api:invitations:read');

    Route::post('/invitations', [InvitationController::class, 'store'])
        ->name('invitations.store')
        ->middleware('internal_api:invitations:create');

    Route::post('/invitations/{id}/cancel', [InvitationController::class, 'cancel'])
        ->name('invitations.cancel')
        ->middleware('internal_api:invitations:cancel');

    Route::get('/police-stations', [PoliceStationController::class, 'index'])
        ->name('police-stations.index');

    Route::get('/police-stations/{id}', [PoliceStationController::class, 'show'])
        ->name('police-stations.show');

    Route::get('/police-stations/{id}/officers', [PoliceStationController::class, 'officers'])
        ->name('police-stations.officers');

    Route::get('/districts', [PoliceStationController::class, 'districts'])
        ->name('districts.index');

    Route::get('/districts/{id}', [DistrictController::class, 'show'])
        ->name('districts.show');

    Route::get('/districts/{id}/neighborhoods', [DistrictController::class, 'neighborhoods'])
        ->name('districts.neighborhoods');

    Route::get('/districts/{id}/stations', [DistrictController::class, 'stations'])
        ->name('districts.stations');

    Route::get('/neighborhoods', [NeighborhoodController::class, 'index'])
        ->name('neighborhoods.index');

    Route::get('/neighborhoods/{id}', [NeighborhoodController::class, 'show'])
        ->name('neighborhoods.show');

    Route::get('/incidents/statistics', [IncidentController::class, 'statistics'])
        ->name('incidents.statistics');

    Route::get('/incidents/map-data', [IncidentController::class, 'mapData'])
        ->name('incidents.map-data');

    Route::get('/incidents/heatmap-data', [IncidentController::class, 'heatmapData'])
        ->name('incidents.heatmap-data');

    Route::get('/incidents', [IncidentController::class, 'index'])
        ->name('incidents.index');

    Route::get('/incidents/{id}', [IncidentController::class, 'show'])
        ->name('incidents.show');
});

Route::prefix('internal')->name('internal.')->middleware(['api'])->group(function () {

    Route::get('/invite/{token}', [InvitationController::class, 'showByToken'])
        ->name('invitations.show-by-token');

    Route::post('/invite/accept', [InvitationController::class, 'acceptByToken'])
        ->name('invitations.accept');
});
