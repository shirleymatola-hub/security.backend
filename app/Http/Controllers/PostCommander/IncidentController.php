<?php

namespace App\Http\Controllers\PostCommander;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Incident;
use App\Models\User;
use App\Http\Requests\ManagerUpdateIncidentRequest;
use App\Services\NotificationService;
use App\Services\MapService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $stationId = auth()->user()->police_station_id;

        $query = Incident::where('police_station_id', $stationId)
            ->with(['category', 'reporter', 'assignee']);

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

        $incidents = $query->latest()->paginate(15);

        return response()->json([
            'incidents' => $incidents,
            // Antes calculado na view (\App\Models\Category::all())
            'categories' => Category::all(),
        ]);
    }

    public function show(Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        $incident->load(['category', 'reporter', 'assignee', 'updates.user', 'attachments', 'policeStation']);

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

        // Antes calculado na view: agentes do posto para o formulário "Atribuir Agente"
        $officers = User::role('police')
            ->where('police_station_id', auth()->user()->police_station_id)
            ->get();

        return response()->json([
            'incident' => $incident,
            'spatial' => $spatial,
            'officers' => $officers,
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

    public function stationIncidents(MapService $mapService): JsonResponse
    {
        $stationId = auth()->user()->police_station_id;
        $geoJson = $mapService->getIncidentsGeoJson($stationId);

        return response()->json($geoJson);
    }

    public function agents(): JsonResponse
    {
        $stationId = auth()->user()->police_station_id;
        $officers = User::where('police_station_id', $stationId)
            ->role('police')
            ->withCount(['assignedIncidents' => function ($query) use ($stationId) {
                $query->where('incidents.police_station_id', $stationId);
            }])
            ->get();

        return response()->json([
            'officers' => $officers,
        ]);
    }
}
