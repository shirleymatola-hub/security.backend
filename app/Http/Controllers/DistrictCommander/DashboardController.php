<?php

namespace App\Http\Controllers\DistrictCommander;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Incident;
use App\Models\Neighborhood;
use App\Models\PoliceStation;
use App\Models\User;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        protected ReminderService $reminderService
    ) {}

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $districtId = $user->district_id;
        $stationIds = PoliceStation::where('district_id', $districtId)->withinStudyArea()->pluck('id');

        $totalIncidents = Incident::whereIn('police_station_id', $stationIds)->count();
        $pending = Incident::whereIn('police_station_id', $stationIds)->where('status', 'pending')->count();
        $investigating = Incident::whereIn('police_station_id', $stationIds)->where('status', 'investigating')->count();
        $resolved = Incident::whereIn('police_station_id', $stationIds)->where('status', 'resolved')->count();
        $thisMonth = Incident::whereIn('police_station_id', $stationIds)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $urgent = Incident::whereIn('police_station_id', $stationIds)
            ->where('priority', 'urgent')
            ->where('status', '!=', 'resolved')
            ->count();

        $byPriority = Incident::whereIn('police_station_id', $stationIds)
            ->select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        $byCategory = Incident::whereIn('police_station_id', $stationIds)
            ->join('categories', 'incidents.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('count(*) as total'))
            ->groupBy('categories.name')
            ->pluck('total', 'name')
            ->toArray();

        $byNeighborhood = Incident::whereIn('police_station_id', $stationIds)
            ->whereNotNull('neighborhood')
            ->select('neighborhood', DB::raw('count(*) as total'))
            ->groupBy('neighborhood')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'neighborhood')
            ->toArray();

        $monthlyTrend = Incident::whereIn('police_station_id', $stationIds)
            ->whereYear('created_at', now()->year)
            ->selectRaw("EXTRACT(MONTH FROM created_at)::int as month_number, count(*) as total")
            ->groupBy('month_number')
            ->pluck('total', 'month_number')
            ->toArray();

        $stats = [
            'total' => $totalIncidents,
            'by_status' => ['pending' => $pending, 'investigating' => $investigating, 'resolved' => $resolved],
            'this_month' => $thisMonth,
            'urgent' => $urgent,
            'by_priority' => $byPriority,
            'by_category' => $byCategory,
            'by_neighborhood' => $byNeighborhood,
            'monthly_trend' => $monthlyTrend,
        ];

        $totalStations = PoliceStation::where('district_id', $districtId)->withinStudyArea()->where('is_active', true)->count();
        $totalOfficers = User::whereIn('police_station_id', $stationIds)->count();

        $stations = PoliceStation::with('commander')
            ->where('district_id', $districtId)
            ->withinStudyArea()
            ->where('is_active', true)
            ->where('tipo_posto', 'Esquadra da PRM')
            ->withCount('incidents')
            ->withCount('officers')
            ->get();

        $recentIncidents = Incident::whereIn('police_station_id', $stationIds)
            ->with(['category', 'policeStation'])
            ->latest()
            ->limit(10)
            ->get();

        $recentIncidentsJson = $recentIncidents->map(fn($i) => [
            'id' => $i->id,
            'reference_code' => $i->reference_code,
            'title' => $i->title,
            'category' => $i->category?->name ?? '',
            'priority' => $i->priority,
            'status' => $i->status,
            'neighborhood' => $i->neighborhood ?? '',
            'station_name' => $i->policeStation?->name ?? '',
            'latitude' => (float) $i->latitude,
            'longitude' => (float) $i->longitude,
            'incident_date' => $i->incident_date?->format('d/m/Y H:i') ?? '',
        ])->toArray();

        $mapIncidentsJson = Incident::whereIn('police_station_id', $stationIds)
            ->with(['category:id,name', 'policeStation:id,name'])
            ->get()
            ->map(fn($i) => [
                'id' => $i->id,
                'reference_code' => $i->reference_code,
                'title' => $i->title,
                'category' => $i->category?->name ?? '',
                'priority' => $i->priority,
                'status' => $i->status,
                'neighborhood' => $i->neighborhood ?? '',
                'station_name' => $i->policeStation?->name ?? '',
                'latitude' => $i->latitude !== null ? (float) $i->latitude : null,
                'longitude' => $i->longitude !== null ? (float) $i->longitude : null,
            ])
            ->toArray();

        $district = District::find($districtId);

        $districtBoundary = null;
        if ($district && $district->geometry) {
            $row = DB::selectOne("SELECT ST_AsGeoJSON(geometry) AS geometry FROM districts WHERE id = ?", [$districtId]);
            if ($row) {
                $districtBoundary = [
                    'type' => 'Feature',
                    'geometry' => json_decode($row->geometry, true),
                    'properties' => ['name' => $district->name],
                ];
            }
        }

        $neighborhoods = [];
        $neighborhoodRows = DB::select("
            SELECT id, name, ST_AsGeoJSON(geometry) AS geometry
            FROM neighborhoods
            WHERE district_id = ? AND geometry IS NOT NULL
        ", [$districtId]);

        foreach ($neighborhoodRows as $row) {
            $neighborhoods[] = [
                'type' => 'Feature',
                'id' => $row->id,
                'geometry' => json_decode($row->geometry, true),
                'properties' => ['id' => $row->id, 'name' => $row->name],
            ];
        }

        $notifications = $user->notifications()->latest()->limit(5)->get();

        $reminders = $this->reminderService->getReminders($user);

        // A view usava auth()->user()->district->name (relação inexistente em User → sempre vazio).
        $districtName = $district?->name ?? '';

        return response()->json(compact(
            'stats',
            'stations',
            'districtId',
            'districtName',
            'recentIncidents',
            'recentIncidentsJson',
            'mapIncidentsJson',
            'totalStations',
            'totalOfficers',
            'districtBoundary',
            'neighborhoods',
            'notifications',
            'reminders'
        ));
    }
}
