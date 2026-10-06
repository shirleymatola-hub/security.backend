<?php

namespace App\Http\Controllers\SquadCommander;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Incident;
use App\Models\PoliceStation;
use App\Models\User;
use App\Http\Requests\ManagerUpdateIncidentRequest;
use App\Services\NotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidentController extends Controller
{
    use AuthorizesRequests;

    private function getCommanderStation()
    {
        $stationId = auth()->user()->police_station_id;
        if (!$stationId) {
            abort(403, 'Nenhuma esquadra associada ao seu utilizador.');
        }
        return PoliceStation::findOrFail($stationId);
    }

    public function index(Request $request): JsonResponse
    {
        $station = $this->getCommanderStation();

        $query = Incident::with(['category', 'reporter', 'assignee'])
            ->where('police_station_id', $station->id);

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
            'station' => $station,
            // A view listava \App\Models\Category::all() no filtro de categorias.
            'categories' => Category::all(),
        ]);
    }

    public function show(Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        $station = $this->getCommanderStation();

        if ($incident->police_station_id !== $station->id) {
            abort(403, 'Acesso negado a esta ocorrência.');
        }

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

        // Lista de agentes do formulário "Atribuir Agente" (antes calculada na view).
        $officers = User::role('police')
            ->where('police_station_id', $incident->police_station_id)
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

        $station = $this->getCommanderStation();

        if ($incident->police_station_id !== $station->id) {
            abort(403, 'Acesso negado a esta ocorrência.');
        }

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

        $station = $this->getCommanderStation();

        if ($incident->police_station_id !== $station->id) {
            abort(403, 'Acesso negado a esta ocorrência.');
        }

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
        // district/commander: a view acedia a $station->district e $station->commander (lazy load).
        // officers_count: a view mostrava $station->officers_count, que nunca era carregado (aparecia sempre 0).
        $station = $this->getCommanderStation()
            ->load(['district', 'commander'])
            ->loadCount(['incidents', 'officers']);

        return response()->json([
            'stations' => collect([$station]),
            'station' => $station,
        ]);
    }
}
