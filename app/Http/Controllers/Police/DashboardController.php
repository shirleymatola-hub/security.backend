<?php

namespace App\Http\Controllers\Police;

use App\Http\Controllers\Controller;
use App\Services\IncidentService;
use App\Services\MapService;
use App\Services\ReminderService;
use App\Models\PoliceStation;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected IncidentService $incidentService,
        protected MapService $mapService,
        protected ReminderService $reminderService,
    ) {
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $stationId = $user->police_station_id;
        $userId = $user->id;

        $stats = $this->incidentService->getStatistics($stationId);

        $myIncidents = \App\Models\Incident::where('police_station_id', $stationId)
            ->with('category')
            ->latest()
            ->limit(10)
            ->get();

        $station = PoliceStation::where('id', $stationId)->first();

        $stationCoverage = $this->mapService->getStationCoverage($stationId);

        $agentIncidents = $this->mapService->getIncidentsGeoJson($stationId);

        $stations = $this->mapService->getStationsGeoJson($stationId);

        $neighborhoods = $this->mapService->getNeighborhoodsGeoJson();

        $reminders = $this->reminderService->getReminders($user);

        return response()->json([
            'stats' => $stats,
            'myIncidents' => $myIncidents,
            'station' => $station,
            'stationCoverage' => $stationCoverage,
            'agentIncidents' => $agentIncidents,
            'stations' => $stations,
            'neighborhoods' => $neighborhoods,
            'reminders' => $reminders['reminders'],
        ]);
    }
}
