<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Services\SuperAdmin\OperationalDataService;
use Illuminate\Http\JsonResponse;

class DistrictController extends Controller
{
    public function __construct(private OperationalDataService $data)
    {
    }

    /** GET /api/super-admin/districts */
    public function index(): JsonResponse
    {
        return response()->json(['districts' => $this->data->districts()]);
    }

    /** GET /api/super-admin/districts/{id} — distrito, postos e bairros. */
    public function show(int $id): JsonResponse
    {
        $district = District::withCount(['neighborhoods', 'policeStations'])->find($id);

        if (! $district) {
            return response()->json(['message' => 'Distrito não encontrado.'], 404);
        }

        $stations = $district->policeStations()
            ->select('id', 'name', 'code', 'address', 'latitude', 'longitude', 'is_active')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'is_active' => (bool) $s->is_active,
            ]);

        $neighborhoods = $district->neighborhoods()
            ->select('id', 'name', 'latitude', 'longitude')
            ->orderBy('name')
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'name' => $n->name,
                'latitude' => $n->latitude !== null ? (float) $n->latitude : null,
                'longitude' => $n->longitude !== null ? (float) $n->longitude : null,
            ]);

        return response()->json([
            'district' => [
                'id' => $district->id,
                'name' => $district->name,
                'province' => $district->province,
                'latitude' => $district->latitude !== null ? (float) $district->latitude : null,
                'longitude' => $district->longitude !== null ? (float) $district->longitude : null,
                'neighborhoods_count' => $district->neighborhoods_count,
                'police_stations_count' => $district->police_stations_count,
            ],
            'stations' => $stations->values(),
            'neighborhoods' => $neighborhoods->values(),
        ]);
    }
}
