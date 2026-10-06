<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\District;
use Illuminate\Http\JsonResponse;

class DistrictController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $district = District::withCount(['neighborhoods', 'policeStations'])
            ->find($id);

        if (!$district) {
            return response()->json([
                'success' => false,
                'message' => 'Distrito não encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $district->id,
                'name' => $district->name,
                'province' => $district->province,
                'latitude' => $district->latitude ? (float) $district->latitude : null,
                'longitude' => $district->longitude ? (float) $district->longitude : null,
                'boundary' => $district->boundary ? json_decode($district->boundary, true) : null,
                'neighborhoods_count' => $district->neighborhoods_count,
                'police_stations_count' => $district->police_stations_count,
                'created_at' => $district->created_at,
                'updated_at' => $district->updated_at,
            ],
        ]);
    }

    public function neighborhoods(int $id): JsonResponse
    {
        $district = District::find($id);

        if (!$district) {
            return response()->json([
                'success' => false,
                'message' => 'Distrito não encontrado.',
            ], 404);
        }

        $neighborhoods = $district->neighborhoods()
            ->select('id', 'name', 'district_id', 'latitude', 'longitude', 'boundary')
            ->orderBy('name')
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'name' => $n->name,
                'district_id' => $n->district_id,
                'latitude' => $n->latitude ? (float) $n->latitude : null,
                'longitude' => $n->longitude ? (float) $n->longitude : null,
                'boundary' => $n->boundary ? json_decode($n->boundary, true) : null,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'district_id' => $district->id,
                'district_name' => $district->name,
                'neighborhoods_count' => $neighborhoods->count(),
                'neighborhoods' => $neighborhoods,
            ],
        ]);
    }

    public function stations(int $id): JsonResponse
    {
        $district = District::find($id);

        if (!$district) {
            return response()->json([
                'success' => false,
                'message' => 'Distrito não encontrado.',
            ], 404);
        }

        $stations = $district->policeStations()
            ->select('id', 'name', 'code', 'address', 'latitude', 'longitude', 'is_active')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'address' => $s->address,
                'latitude' => $s->latitude ? (float) $s->latitude : null,
                'longitude' => $s->longitude ? (float) $s->longitude : null,
                'is_active' => $s->is_active,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'district_id' => $district->id,
                'district_name' => $district->name,
                'stations_count' => $stations->count(),
                'stations' => $stations,
            ],
        ]);
    }
}
