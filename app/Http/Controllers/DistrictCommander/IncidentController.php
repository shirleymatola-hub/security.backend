<?php

namespace App\Http\Controllers\DistrictCommander;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\District;
use App\Models\Incident;
use App\Models\PoliceStation;
use App\Models\User;
use App\Http\Requests\ManagerUpdateIncidentRequest;
use App\Services\MapService;
use App\Services\NotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $districtId = $user->district_id;
        $stationIds = PoliceStation::where('district_id', $districtId)->withinStudyArea()->pluck('id');

        $query = Incident::whereIn('police_station_id', $stationIds)
            ->with(['category', 'reporter', 'assignee', 'policeStation']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('police_station_id')) {
            $query->where('police_station_id', $request->police_station_id);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $incidents = $query->latest()->paginate(15);
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $stations = PoliceStation::where('district_id', $districtId)->withinStudyArea()->where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'incidents' => $incidents,
            'categories' => $categories,
            'stations' => $stations,
            // A view listava \App\Models\Category::all() no filtro de categorias.
            'allCategories' => Category::all(),
        ]);
    }

    public function show(Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        // policeStation.commander: a view acedia a $incident->policeStation->commander (lazy load).
        $incident->load(['category', 'reporter', 'assignee', 'updates.user', 'attachments', 'policeStation.commander']);

        $spatial = null;

        if ($incident->police_station_id && $incident->latitude && $incident->longitude) {
            $row = DB::selectOne("
                SELECT ROUND(
                    ST_Distance(
                        ST_SetSRID(ST_MakePoint(i.longitude, i.latitude), 4326)::geography,
                        ps.geometry::geography
                    )::numeric, 2
                ) AS distance_meters
                FROM incidents i
                CROSS JOIN police_stations ps
                WHERE i.id = ? AND ps.id = ?
            ", [$incident->id, $incident->police_station_id]);

            if ($row) {
                $distance = (float) $row->distance_meters;
                $spatial = [
                    'station_name' => $incident->policeStation->name ?? '-',
                    'distance' => $distance,
                    'formatted_distance' => number_format($distance, 2, ',', '.'),
                    'within_coverage' => $distance <= 1000,
                ];
            }
        }

        return response()->json([
            'incident' => $incident,
            'spatial' => $spatial,
        ]);
    }

    public function update(ManagerUpdateIncidentRequest $request, Incident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $incident->updates()->create([
            'user_id' => auth()->id(),
            'status' => $request->status,
            'observation' => $request->observation,
        ]);

        $incident->update([
            'status' => $request->status,
            'priority' => $request->priority,
        ]);

        if ($request->status === 'resolved') {
            $incident->update(['resolved_at' => now()]);
            NotificationService::notifyResolved($incident, $request->observation);
        } else {
            NotificationService::notifyStatusChanged($incident, $request->observation);
        }

        return response()->json([
            'message' => 'Ocorrência atualizada com sucesso.',
            'incident' => $incident->fresh(),
        ]);
    }

    public function assign(Request $request, Incident $incident): JsonResponse
    {
        $this->authorize('assign', $incident);

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $incident->update($validated);

        NotificationService::notifyAssignedOfficer($incident, $validated['assigned_to']);

        $officer = User::find($validated['assigned_to']);
        if ($officer && $officer->hasRole('sernic_officer')) {
            NotificationService::notifyForwardedToSernic($incident, 'Atribuído ao SERNIC para investigação criminal.');
        }

        return response()->json([
            'message' => 'Ocorrência atribuída com sucesso.',
            'incident' => $incident->fresh(),
        ]);
    }

    public function stations(): JsonResponse
    {
        $user = auth()->user();
        $districtId = $user->district_id;

        $stations = PoliceStation::with('commander')
            ->where('district_id', $districtId)
            ->withinStudyArea()
            ->where('is_active', true)
            ->where('tipo_posto', 'Esquadra da PRM')
            ->withCount('incidents')
            ->withCount('officers')
            ->orderBy('name')
            ->get();

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

        return response()->json([
            'stations' => $stations,
            'districtBoundary' => $districtBoundary,
            'neighborhoods' => $neighborhoods,
            // Subtítulo de impressão do mapa (a view usava auth()->user()->district->name, sempre vazio).
            'districtName' => $district?->name ?? '',
        ]);
    }

    public function incidentsJson(MapService $mapService): JsonResponse
    {
        $districtId = auth()->user()->district_id;
        $stationIds = PoliceStation::where('district_id', $districtId)->withinStudyArea()->pluck('id')->toArray();

        $incidents = Incident::whereIn('police_station_id', $stationIds)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['category:id,name,slug,color,icon', 'policeStation:id,name'])
            ->get();

        $features = $incidents->map(function ($incident) {
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
                    'category_icon' => $category?->icon ?? 'warning',
                    'address' => $incident->address_detail,
                    'neighborhood' => $incident->neighborhood,
                    'incident_date' => $incident->incident_date?->format('d/m/Y H:i'),
                    'station_name' => $incident->policeStation?->name ?? '',
                    'marker_color' => config("map.marker_colors.{$incident->priority}", '#115cb9'),
                ],
            ];
        });

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features->toArray(),
        ]);
    }
}
