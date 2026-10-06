<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\PoliceStation;
use App\Models\User;
use App\Services\IncidentService;
use App\Services\MapService;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected IncidentService $incidentService,
        protected MapService $mapService,
        protected ReminderService $reminderService,
    ) {}

    /**
     * GET /api/manager/dashboard — dados de manager/dashboard.blade.php.
     * "neighborhoods" é o mesmo GeoJSON de /api/map/neighborhoods (o frontend usa-o
     * diretamente para a camada de nomes dos bairros).
     */
    public function index(): JsonResponse
    {
        $stationId = auth()->user()->police_station_id;
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);

        $stats = $this->incidentService->getStatistics($stationId, $year, $month);
        $station = PoliceStation::with('commander')->find($stationId);

        $pendingIncidents = Incident::where('police_station_id', $stationId)
            ->where('status', 'pending')
            ->with(['category', 'reporter'])
            ->latest()
            ->get();

        $undispatchedIncidents = Incident::where('police_station_id', $stationId)
            ->whereNull('assigned_to')
            ->where('status', 'pending')
            ->with(['category', 'reporter'])
            ->latest()
            ->get();

        $officers = User::where('police_station_id', $stationId)
            ->whereHas('roles', fn($q) => $q->where('name', 'police'))
            ->get();

        // Correção: getStationCoverage(int) dava TypeError se o utilizador não tivesse posto.
        $stationCoverage = $stationId ? $this->mapService->getStationCoverage($stationId) : null;
        $agentIncidents = $this->mapService->getIncidentsGeoJson($stationId);
        $stations = $this->mapService->getStationsGeoJson($stationId);
        $neighborhoods = $this->mapService->getNeighborhoodsGeoJson();

        $newIncidentsCount = Incident::where('police_station_id', $stationId)
            ->where('status', 'pending')
            ->whereNull('assigned_to')
            ->count();

        $reminders = $this->reminderService->getReminders(auth()->user());

        return response()->json([
            'stats' => $stats,
            'stationId' => $stationId,
            'station' => $station,
            'year' => $year,
            'month' => $month,
            'withLocation' => $stats['with_location'],
            'withoutLocation' => $stats['without_location'],
            'pendingIncidents' => $pendingIncidents,
            'undispatchedIncidents' => $undispatchedIncidents,
            'officers' => $officers,
            'stationCoverage' => $stationCoverage,
            'agentIncidents' => $agentIncidents,
            'stations' => $stations,
            'neighborhoods' => $neighborhoods,
            'newIncidentsCount' => $newIncidentsCount,
            'reminders' => $reminders['reminders'],
        ]);
    }
}
