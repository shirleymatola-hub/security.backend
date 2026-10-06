<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\SquadCommanderMapController;
use App\Http\Controllers\Api\JurisdictionController;
use App\Http\Controllers\Api\PoliceUnitController;
use App\Http\Controllers\Api\AreaDeEstudoController;
use App\Http\Controllers\Api\AreaAtuacaoController;

// Incluído em routes/api.php → URL final: /api/map/..., /api/jurisdictions, ...
// Estes caminhos são usados pelos scripts do mapa (public/js do frontend).
Route::name('api.')->group(function () {
    // Map configuration and data
    Route::get('/map/config', [MapController::class, 'config'])->name('map.config');
    Route::get('/map/incidents', [MapController::class, 'incidents'])->name('map.incidents');

    // Police stations (esquadras) — main 4 esquadras only
    Route::get('/map/stations', [MapController::class, 'stations'])->name('map.stations');

    // SERNIC unit (Investigação Criminal)
    Route::get('/map/sernic', [MapController::class, 'sernic'])->name('map.sernic');

    Route::get('/map/stations/coverage', [MapController::class, 'allStationsCoverage'])->name('map.stations.coverage');
    Route::get('/map/neighborhoods', [MapController::class, 'neighborhoods'])->name('map.neighborhoods');
    Route::get('/map/heatmap', [MapController::class, 'heatmap'])->name('map.heatmap');
    Route::get('/map/geocode/{lat}/{lng}', [MapController::class, 'geocode'])->name('map.geocode');
    Route::get('/map/district/{name}', [MapController::class, 'districtBoundary'])->name('map.district');

    // Public incident routes
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    // Authenticated incident detail
    Route::middleware('auth:sanctum')
        ->get('/incidents/{incident}', [IncidentController::class, 'show'])
        ->name('incidents.show');
    Route::get('/categories', [IncidentController::class, 'categories'])->name('categories');
    //Route of nearest
    Route::get('/nearest-station', [RouteController::class, 'nearestStation']);

    // Route directions
    Route::match(['get', 'post'], '/route', [RouteController::class, 'index'])
        ->name('route');

    // Jurisdictions - Area de Estudo
    Route::get('/jurisdictions', [JurisdictionController::class, 'index'])->name('jurisdictions');
    Route::get('/jurisdictions/station/{stationId}', [JurisdictionController::class, 'byStation'])->name('jurisdictions.station');
    Route::get('/jurisdictions/summary', [JurisdictionController::class, 'summary'])->name('jurisdictions.summary');

    // Area de Estudo - direct from "Area de Estudo" table
    Route::get('/area-de-estudo', [AreaDeEstudoController::class, 'index'])->name('area-de-estudo');

    // Authenticated station map
    Route::middleware('auth:sanctum')
        ->get('/map/station/{stationId}', [MapController::class, 'stationIncidents'])
        ->name('map.station');

    // Area de atuação — user-scoped, auth-derived (no station param from frontend)
    Route::middleware('auth:sanctum')
        ->get('/area-de-atuacao', [AreaAtuacaoController::class, 'index'])
        ->name('area-de-atuacao');

    // Station coverage buffer
    Route::get('/map/station/{stationId}/coverage', [MapController::class, 'stationCoverage'])
        ->name('map.station.coverage');

    // Police units (subunidades/postos)
    Route::get('/police-units', [PoliceUnitController::class, 'index'])->name('police-units');
    Route::get('/police-units/jurisdictions', [PoliceUnitController::class, 'jurisdictions'])->name('police-units.jurisdictions');

    // Squad commander filtered map data
    Route::middleware(['auth:sanctum', 'role:squad_commander'])
        ->prefix('map/squad-commander')
        ->name('map.squad-commander.')
        ->group(function () {
            Route::get('/incidents', [SquadCommanderMapController::class, 'incidents'])->name('incidents');
            Route::get('/station', [SquadCommanderMapController::class, 'station'])->name('station');
            Route::get('/coverage', [SquadCommanderMapController::class, 'stationCoverage'])->name('coverage');
        });
});