<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function __construct(
        private IncidentService $incidentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Incident::query()
            ->with([
                'category:id,name,slug,color,icon',
                'policeStation:id,name,code',
                'neighborhoodRel:id,name',
            ])
            ->withCount('updates');

        if ($request->filled('status')) {
            $query->where('incidents.status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('incidents.priority', $request->input('priority'));
        }

        if ($request->filled('police_station_id')) {
            $query->where('incidents.police_station_id', $request->input('police_station_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('incidents.category_id', $request->input('category_id'));
        }

        if ($request->filled('neighborhood_id')) {
            $query->where('incidents.neighborhood_id', $request->input('neighborhood_id'));
        }

        if ($request->filled('district_id')) {
            $query->whereHas('policeStation', function ($q) use ($request) {
                $q->where('police_stations.district_id', $request->input('district_id'));
            });
        }

        if ($request->filled('date_from')) {
            $query->where('incidents.incident_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('incidents.incident_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('incidents.title', 'ilike', "%{$search}%")
                    ->orWhere('incidents.reference_code', 'ilike', "%{$search}%")
                    ->orWhere('incidents.address_detail', 'ilike', "%{$search}%")
                    ->orWhere('incidents.neighborhood', 'ilike', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $incidents = $query->orderBy('incidents.created_at', 'desc')
            ->paginate($perPage);

        $data = $incidents->through(fn ($incident) => [
            'id' => $incident->id,
            'reference_code' => $incident->reference_code,
            'title' => $incident->title,
            'category' => $incident->category ? [
                'id' => $incident->category->id,
                'name' => $incident->category->name,
                'color' => $incident->category->color,
            ] : null,
            'priority' => $incident->priority,
            'status' => $incident->status,
            'latitude' => $incident->latitude ? (float) $incident->latitude : null,
            'longitude' => $incident->longitude ? (float) $incident->longitude : null,
            'address_detail' => $incident->address_detail,
            'neighborhood' => $incident->neighborhood,
            'neighborhood_id' => $incident->neighborhood_id,
            'police_station_id' => $incident->police_station_id,
            'police_station' => $incident->policeStation ? [
                'id' => $incident->policeStation->id,
                'name' => $incident->policeStation->name,
                'code' => $incident->policeStation->code,
            ] : null,
            'incident_date' => $incident->incident_date,
            'resolved_at' => $incident->resolved_at,
            'is_anonymous' => $incident->is_anonymous,
            'is_public' => $incident->is_public,
            'created_at' => $incident->created_at,
            'updates_count' => $incident->updates_count,
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $incident = Incident::with([
            'category:id,name,slug,color,icon',
            'policeStation:id,name,code,address,district_id',
            'policeStation.district:id,name',
            'neighborhoodRel:id,name',
        ])
            ->withCount('updates')
            ->withCount('attachments')
            ->find($id);

        if (!$incident) {
            return response()->json([
                'success' => false,
                'message' => 'Ocorrência não encontrada.',
            ], 404);
        }

        $data = [
            'id' => $incident->id,
            'reference_code' => $incident->reference_code,
            'title' => $incident->title,
            'description' => $incident->description,
            'category' => $incident->category ? [
                'id' => $incident->category->id,
                'name' => $incident->category->name,
                'slug' => $incident->category->slug,
                'color' => $incident->category->color,
                'icon' => $incident->category->icon,
            ] : null,
            'priority' => $incident->priority,
            'status' => $incident->status,
            'latitude' => $incident->latitude ? (float) $incident->latitude : null,
            'longitude' => $incident->longitude ? (float) $incident->longitude : null,
            'address_detail' => $incident->address_detail,
            'neighborhood' => $incident->neighborhood,
            'neighborhood_id' => $incident->neighborhood_id,
            'police_station_id' => $incident->police_station_id,
            'police_station' => $incident->policeStation ? [
                'id' => $incident->policeStation->id,
                'name' => $incident->policeStation->name,
                'code' => $incident->policeStation->code,
                'address' => $incident->policeStation->address,
                'district' => $incident->policeStation->district ? [
                    'id' => $incident->policeStation->district->id,
                    'name' => $incident->policeStation->district->name,
                ] : null,
            ] : null,
            'incident_date' => $incident->incident_date,
            'resolved_at' => $incident->resolved_at,
            'is_anonymous' => $incident->is_anonymous,
            'is_public' => $incident->is_public,
            'updates_count' => $incident->updates_count,
            'attachments_count' => $incident->attachments_count,
            'created_at' => $incident->created_at,
            'updated_at' => $incident->updated_at,
        ];

        if (!$incident->is_anonymous) {
            $data['reported_by'] = $incident->reported_by;
            $data['assigned_to'] = $incident->assigned_to;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $stationId = $request->input('police_station_id') ? (int) $request->input('police_station_id') : null;
        $year = $request->input('year') ? (int) $request->input('year') : null;
        $month = $request->input('month') ? (int) $request->input('month') : null;

        $stats = $this->incidentService->getStatistics($stationId, $year, $month);

        $recentIncidents = collect($stats['recent_incidents'])->map(fn ($incident) => [
            'id' => $incident->id,
            'reference_code' => $incident->reference_code,
            'title' => $incident->title,
            'priority' => $incident->priority,
            'status' => $incident->status,
            'created_at' => $incident->created_at,
        ])->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $stats['total'],
                'station_total' => $stats['station_total'],
                'with_location' => $stats['with_location'],
                'without_location' => $stats['without_location'],
                'by_status' => $stats['by_status'],
                'by_priority' => $stats['by_priority'],
                'by_category' => $stats['by_category'],
                'this_month' => $stats['this_month'],
                'percentage_change' => $stats['percentage_change'],
                'pending' => $stats['pending'],
                'investigating' => $stats['investigating'],
                'resolved' => $stats['resolved'],
                'archived' => $stats['archived'],
                'recent_incidents' => $recentIncidents,
            ],
        ]);
    }

    public function mapData(Request $request): JsonResponse
    {
        $stationId = $request->input('police_station_id') ? (int) $request->input('police_station_id') : null;

        $rawData = $this->incidentService->getMapData($stationId);

        $data = array_map(fn ($item) => [
            'id' => $item['id'],
            'reference_code' => $item['reference_code'],
            'title' => $item['title'],
            'status' => $item['status'],
            'priority' => $item['priority'],
            'category' => $item['category']['name'] ?? null,
            'category_color' => $item['category']['color'] ?? null,
            'latitude' => (float) $item['latitude'],
            'longitude' => (float) $item['longitude'],
        ], $rawData);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function heatmapData(Request $request): JsonResponse
    {
        $stationId = $request->input('police_station_id') ? (int) $request->input('police_station_id') : null;

        $data = $this->incidentService->getHeatmapData($stationId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
