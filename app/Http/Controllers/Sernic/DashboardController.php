<?php

namespace App\Http\Controllers\Sernic;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\PoliceStation;
use App\Services\MapService;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected MapService $mapService,
        protected ReminderService $reminderService,
    ) {
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();

        $query = Incident::where('assigned_to', $user->id);

        $totalForwarded = (clone $query)->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $investigating = (clone $query)->where('status', 'investigating')->count();
        $resolved = (clone $query)->where('status', 'resolved')->count();

        $recentIncidents = (clone $query)
            ->with('category')
            ->latest()
            ->limit(10)
            ->get();

        $urgentIncidents = (clone $query)
            ->with('category')
            ->whereIn('priority', ['high', 'urgent'])
            ->whereIn('status', ['pending', 'investigating'])
            ->latest()
            ->limit(5)
            ->get();

        $stats = [
            'total' => $totalForwarded,
            'by_status' => ['pending' => $pending, 'investigating' => $investigating, 'resolved' => $resolved],
            'recent_incidents' => $recentIncidents,
        ];

        $forwardedIncidents = Incident::where('assigned_to', $user->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('category:id,name,slug,color,icon')
            ->get();

        $features = $forwardedIncidents->map(function ($incident) {
            $category = $incident->category;
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        (float) $incident->longitude,
                        (float) $incident->latitude,
                    ],
                ],
                'properties' => [
                    'id' => $incident->id,
                    'reference_code' => $incident->reference_code,
                    'title' => $incident->title,
                    'description' => $incident->description,
                    'priority' => $incident->priority,
                    'status' => $incident->status,
                    'category' => $category?->name,
                    'category_color' => $category?->color ?? '#115cb9',
                    'address' => $incident->address_detail,
                    'neighborhood' => $incident->neighborhood,
                    'incident_date' => $incident->incident_date?->format('d/m/Y H:i'),
                    'marker_color' => config("map.marker_colors.{$incident->priority}", '#115cb9'),
                ],
            ];
        });

        $agentIncidents = [
            'type' => 'FeatureCollection',
            'features' => $features->toArray(),
        ];

        $stations = $this->mapService->getStationsGeoJson(null, true);
        $neighborhoods = $this->mapService->getNeighborhoodsGeoJson();

        $sernicUnit = null;
        if ($user->police_station_id) {
            $sernicUnit = PoliceStation::where('id', $user->police_station_id)
                ->where('is_active', true)
                ->first(['id', 'name', 'address', 'phone', 'latitude', 'longitude']);
        }

        $reminders = $this->reminderService->getReminders($user);

        return response()->json([
            'stats' => $stats,
            'recentIncidents' => $recentIncidents,
            'urgentIncidents' => $urgentIncidents,
            'agentIncidents' => $agentIncidents,
            'stations' => $stations,
            'neighborhoods' => $neighborhoods,
            'sernicUnit' => $sernicUnit,
            'reminders' => $reminders['reminders'],
        ]);
    }
}
