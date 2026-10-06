<?php

namespace App\Http\Controllers\SquadCommander;

use App\Http\Controllers\Controller;
use App\Models\PoliceStation;
use App\Models\Category;
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

        if (!$stationId) {
            abort(403, 'Nenhuma esquadra associada ao seu utilizador.');
        }

        $station = PoliceStation::withCount('incidents')
            ->with(['district', 'commander'])
            ->find($stationId);

        if (!$station) {
            abort(403, 'Nenhuma esquadra associada ao seu utilizador.');
        }

        $stats = $this->incidentService->getStatistics($station->id);

        $officersCount = $station->officers()->count();

        $categories = Category::whereHas('incidents', function ($q) use ($station) {
            $q->where('police_station_id', $station->id);
        })->withCount(['incidents' => function ($q) use ($station) {
            $q->where('police_station_id', $station->id);
        }])->orderByDesc('incidents_count')->get();

        $reminders = $this->reminderService->getReminders($user);

        return response()->json(compact('station', 'stats', 'officersCount', 'categories', 'reminders'));
    }
}
