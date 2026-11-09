<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Neighborhood;
use Illuminate\Http\JsonResponse;

class NeighborhoodController extends Controller
{
    /** GET /api/super-admin/neighborhoods?page= */
    public function index(): JsonResponse
    {
        $neighborhoods = Neighborhood::query()
            ->select('id', 'name', 'district_id', 'latitude', 'longitude')
            ->with('district:id,name')
            ->orderBy('name')
            ->paginate(15);

        $neighborhoods->getCollection()->transform(fn ($n) => [
            'id' => $n->id,
            'name' => $n->name,
            'district_id' => $n->district_id,
            'district_name' => $n->district?->name,
            'latitude' => $n->latitude !== null ? (float) $n->latitude : null,
            'longitude' => $n->longitude !== null ? (float) $n->longitude : null,
        ]);

        return response()->json($neighborhoods);
    }

    /** GET /api/super-admin/neighborhoods/{id} — bairro e postos associados. */
    public function show(int $id): JsonResponse
    {
        $neighborhood = Neighborhood::with('district:id,name')->find($id);

        if (! $neighborhood) {
            return response()->json(['message' => 'Bairro não encontrado.'], 404);
        }

        $stations = $neighborhood->policeStations()
            ->select('police_stations.id', 'police_stations.name', 'police_stations.code', 'police_stations.is_active')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'is_active' => (bool) $s->is_active,
            ]);

        return response()->json([
            'neighborhood' => [
                'id' => $neighborhood->id,
                'name' => $neighborhood->name,
                'district' => $neighborhood->district ? ['id' => $neighborhood->district->id, 'name' => $neighborhood->district->name] : null,
                'latitude' => $neighborhood->latitude !== null ? (float) $neighborhood->latitude : null,
                'longitude' => $neighborhood->longitude !== null ? (float) $neighborhood->longitude : null,
                'police_stations_count' => $stations->count(),
                'police_stations' => $stations->values(),
            ],
        ]);
    }
}
