<?php

namespace App\Http\Controllers\PostCommander;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\PoliceStation;
use App\Services\IncidentService;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected IncidentService $incidentService,
        protected ReminderService $reminderService
    ) {}

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $stationId = $user->police_station_id;
        $stats = $this->incidentService->getStatistics($stationId);
        $station = PoliceStation::with('commander')->find($stationId);
        $officers = $station ? $station->officers()->with('assignedIncidents')->get() : collect();

        $recentIncidents = Incident::where('police_station_id', $stationId)
            ->with('category')
            ->latest()
            ->limit(10)
            ->get();

        $reminders = $this->reminderService->getReminders($user);

        return response()->json([
            'stats' => $stats,
            'stationId' => $stationId,
            'station' => $station,
            'officers' => $officers,
            'recentIncidents' => $recentIncidents,
            'reminders' => $reminders['reminders'],
        ]);
    }
}
