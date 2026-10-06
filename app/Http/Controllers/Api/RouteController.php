<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\PoliceStation;

class RouteController extends Controller
{
    public function __construct(
        protected OpenRouteService $routeService
    ) {
    }

    /**
     * Get directions between two points.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'origin_lat' => 'required|numeric|between:-90,90',
                'origin_lng' => 'required|numeric|between:-180,180',
                'destination_lat' => 'required|numeric|between:-90,90',
                'destination_lng' => 'required|numeric|between:-180,180',
                'profile' => 'nullable|string|in:driving-car,driving-hgv,foot-walking,foot-hiking,wheelchair,mota',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Parâmetros inválidos',
                'details' => $e->errors(),
            ], 422);
        }

        try {
            $profile = $validated['profile'] ?? 'driving-car';
            $orsProfile = $profile === 'mota' ? 'driving-car' : $profile;

            $geojson = $this->routeService->getDirections(
                originLat: (float) $validated['origin_lat'],
                originLng: (float) $validated['origin_lng'],
                destLat: (float) $validated['destination_lat'],
                destLng: (float) $validated['destination_lng'],
                profile: $orsProfile,
            );

            return response()->json($geojson);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao calcular rota',
                'message' => $e->getMessage(),
            ], 502);
        }

    }
    public function nearestStation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'exclude_station_id' => 'nullable|integer',
        ]);

        $lat = $request->lat;
        $lng = $request->lng;
        $excludeId = $request->input('exclude_station_id');

        $query = PoliceStation::where('is_active', true)
            ->withinStudyArea()
            ->select('*')
            ->selectRaw(
                "ST_Distance(
                    geometry::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) AS distance",
                [$lng, $lat]
            );

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $stations = $query->orderBy('distance')
            ->limit(5)
            ->get();

        return response()->json([
            'stations' => $stations->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'address' => $s->address,
                'phone' => $s->phone,
                'latitude' => $s->latitude,
                'longitude' => $s->longitude,
                'distance' => round($s->distance, 2),
            ])->values(),
            'query' => ['lat' => $lat, 'lng' => $lng],
        ]);
    }
}