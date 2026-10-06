<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Incident;
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

    /**
     * GET /api/manager/incidents — lista paginada (manager/incidents/index.blade.php).
     * "categories" substitui o \App\Models\Category::all() que a view fazia.
     */
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
            'categories' => Category::all(),
        ]);
    }

    public function show(Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        $incident->load(['category', 'reporter', 'assignee', 'updates.user', 'attachments', 'policeStation', 'events.user']);

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

        $stationOfficers = User::where('police_station_id', auth()->user()->police_station_id)
            ->whereHas('roles', fn($q) => $q->where('name', 'police'))
            ->get();

        $sernicOfficers = User::whereHas('roles', fn($q) => $q->where('name', 'sernic_officer'))
            ->get();

        return response()->json([
            'incident' => $incident,
            'spatial' => $spatial,
            'stationOfficers' => $stationOfficers,
            'sernicOfficers' => $sernicOfficers,
        ]);
    }

    // edit(): removido — usava a view manager.incidents.edit, que nunca existiu
    // (o formulário de atualização está na página de detalhe).

    public function update(ManagerUpdateIncidentRequest $request, Incident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $oldStatus = $incident->status;
        $oldPriority = $incident->priority;

        $incident->updates()->create([
            'user_id' => auth()->id(),
            'status' => $request->status,
            'observation' => $request->observation,
        ]);

        $incident->update([
            'status' => $request->status,
            'priority' => $request->priority,
        ]);

        if ($oldStatus !== $request->status) {
            $incident->logEvent('status_changed', $request->observation, auth()->id(), $oldStatus, $request->status);
        }

        if ($oldPriority !== $request->priority) {
            $incident->logEvent('priority_changed', "Prioridade alterada de {$oldPriority} para {$request->priority}", auth()->id(), $oldPriority, $request->priority);
        }

        if ($request->status === 'resolved') {
            $incident->update(['resolved_at' => now()]);
            $incident->logEvent('resolved', $request->observation, auth()->id());
            NotificationService::notifyResolved($incident, $request->observation);
        } else {
            NotificationService::notifyStatusChanged($incident, $request->observation);
        }

        return response()->json([
            'message' => 'Ocorrencia atualizada com sucesso.',
            'incident' => $incident->fresh(),
        ]);
    }

    public function assign(Request $request, Incident $incident): JsonResponse
    {
        $this->authorize('assign', $incident);

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'observation' => 'nullable|string',
        ]);

        $officer = User::find($validated['assigned_to']);
        $oldAssigned = $incident->assigned_to;

        $incident->update(['assigned_to' => $validated['assigned_to'], 'status' => 'investigating']);

        $incident->logEvent(
            'assigned',
            $validated['observation'] ?? "Atribuida a {$officer->name}",
            auth()->id(),
            $oldAssigned ? (string) $oldAssigned : null,
            (string) $validated['assigned_to'],
            ['assigned_to_name' => $officer->name]
        );

        NotificationService::notifyAssignedOfficer($incident, $validated['assigned_to']);

        if ($officer && $officer->hasRole('sernic_officer')) {
            $incident->logEvent('forwarded_to_sernic', 'Encaminhada para SERNIC', auth()->id());
            NotificationService::notifyForwardedToSernic($incident, 'Atribuido ao SERNIC para investigacao criminal.');
        }

        return response()->json([
            'message' => 'Ocorrencia atribuida com sucesso.',
            'incident' => $incident->fresh(),
        ]);
    }

    public function forwardToSernic(Request $request, Incident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'reason' => 'required|string|max:500',
            'observation' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $officer = User::find($validated['assigned_to']);
        $oldAssigned = $incident->assigned_to;
        // Correção: $validated['observation'] não existe quando o campo vem vazio (aviso "Undefined array key").
        $observation = $validated['observation'] ?? null;

        $incident->update([
            'assigned_to' => $validated['assigned_to'],
            'status' => 'investigating',
            'priority' => $validated['priority'],
        ]);

        $incident->updates()->create([
            'user_id' => auth()->id(),
            'status' => 'investigating',
            'observation' => "Encaminhada para SERNIC. Motivo: {$validated['reason']}" . ($observation ? " | {$observation}" : ''),
        ]);

        $incident->logEvent(
            'forwarded_to_sernic',
            $validated['reason'],
            auth()->id(),
            $oldAssigned ? (string) $oldAssigned : null,
            (string) $validated['assigned_to'],
            [
                'reason' => $validated['reason'],
                'observation' => $observation,
                'sernic_officer' => $officer->name,
            ]
        );

        NotificationService::notifyForwardedToSernic($incident, $validated['reason']);

        return response()->json([
            'message' => 'Ocorrencia encaminhada para SERNIC com sucesso.',
            'incident' => $incident->fresh(),
        ]);
    }

    public function receive(Incident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $incident->logEvent('received', 'Ocorrencia recebida pelo Chefe de Permanencia', auth()->id());

        return response()->json(['message' => 'Ocorrencia recebida e registrada.']);
    }

    public function markViewed(Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        $alreadyViewed = $incident->events()->where('type', 'viewed')->exists();
        if (!$alreadyViewed) {
            $incident->logEvent('viewed', 'Ocorrencia visualizada pelo Chefe de Permanencia', auth()->id());
        }

        return response()->json(['ok' => true]);
    }

    public function stationIncidents(): JsonResponse
    {
        $stationId = auth()->user()->police_station_id;

        $incidents = Incident::where('police_station_id', $stationId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('category:id,name,slug,color,icon')
            ->get()
            ->map(fn($i) => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $i->longitude, (float) $i->latitude],
                ],
                'properties' => [
                    'id' => $i->id,
                    'reference_code' => $i->reference_code,
                    'title' => $i->title,
                    'description' => $i->description,
                    'priority' => $i->priority,
                    'status' => $i->status,
                    'category' => $i->category?->name,
                    'category_color' => $i->category?->color ?? '#115cb9',
                    'category_icon' => $i->category?->icon ?? 'warning',
                    'address' => $i->address_detail,
                    'neighborhood' => $i->neighborhood,
                    'incident_date' => $i->incident_date?->format('d/m/Y H:i'),
                    'marker_color' => config("map.marker_colors.{$i->priority}", '#115cb9'),
                ],
            ]);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $incidents->toArray(),
        ]);
    }
}
