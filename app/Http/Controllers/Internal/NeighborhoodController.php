<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Neighborhood;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NeighborhoodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $neighborhoods = Neighborhood::select('id', 'name', 'district_id', 'latitude', 'longitude', 'boundary')
            ->with('district:id,name')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        $neighborhoods->getCollection()->transform(fn ($n) => [
            'id' => $n->id,
            'name' => $n->name,
            'district_id' => $n->district_id,
            'district_name' => $n->district?->name,
            'latitude' => $n->latitude ? (float) $n->latitude : null,
            'longitude' => $n->longitude ? (float) $n->longitude : null,
            'boundary' => $n->boundary ? json_decode($n->boundary, true) : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $neighborhoods,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $neighborhood = Neighborhood::with('district:id,name')
            ->find($id);

        if (!$neighborhood) {
            return response()->json([
                'success' => false,
                'message' => 'Bairro não encontrado.',
            ], 404);
        }

        $policeStations = $neighborhood->policeStations()
            ->select('police_stations.id', 'police_stations.name', 'police_stations.code', 'police_stations.latitude', 'police_stations.longitude', 'police_stations.is_active')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'latitude' => $s->latitude ? (float) $s->latitude : null,
                'longitude' => $s->longitude ? (float) $s->longitude : null,
                'is_active' => $s->is_active,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $neighborhood->id,
                'name' => $neighborhood->name,
                'district_id' => $neighborhood->district_id,
                'district' => $neighborhood->district ? [
                    'id' => $neighborhood->district->id,
                    'name' => $neighborhood->district->name,
                ] : null,
                'latitude' => $neighborhood->latitude ? (float) $neighborhood->latitude : null,
                'longitude' => $neighborhood->longitude ? (float) $neighborhood->longitude : null,
                'boundary' => $neighborhood->boundary ? json_decode($neighborhood->boundary, true) : null,
                'police_stations_count' => $policeStations->count(),
                'police_stations' => $policeStations,
                'created_at' => $neighborhood->created_at,
                'updated_at' => $neighborhood->updated_at,
            ],
        ]);
    }
}
