<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function __construct(
        protected MapService $mapService
    ) {}

    /**
     * Get map configuration for Leaflet
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'tile_url' => $this->mapService->getTileUrl(),
            'attribution' => config('map.attribution'),
            'center' => $this->mapService->getDefaultCenter(),
            'bounds' => config('map.bounds'),
            'marker_colors' => config('map.marker_colors'),
        ]);
    }

    /**
     * Get all incidents as GeoJSON for the map
     */
    public function incidents(Request $request): JsonResponse
    {
        $filters = $request->only(['category_id', 'priority', 'status', 'neighborhood']);
        $geoJson = $this->mapService->getIncidentsGeoJson(null, $filters);

        return response()->json($geoJson);
    }

    /**
     * Get incidents for a specific police station
     */
    public function stationIncidents(int $stationId): JsonResponse
    {
        $geoJson = $this->mapService->getIncidentsGeoJson($stationId);

        return response()->json($geoJson);
    }

    /**
     * Get police stations as GeoJSON
     */
    public function stations(Request $request): JsonResponse
    {
        $mainOnly = $request->boolean('main_only', false);
        $geoJson = $this->mapService->getStationsGeoJson(null, $mainOnly);

        return response()->json($geoJson);
    }

    /**
     * Get SERNIC unit as GeoJSON for the map layer.
     * Returns only police stations with tipo_posto = 'Investigação Criminal'.
     */
    public function sernic(): JsonResponse
    {
        $geoJson = $this->mapService->getSernicGeoJson();

        return response()->json($geoJson);
    }

    /**
     * Get neighborhoods as GeoJSON
     */
    public function neighborhoods(): JsonResponse
    {
        $geoJson = $this->mapService->getNeighborhoodsGeoJson();

        return response()->json($geoJson);
    }

    /**
     * Get heatmap data
     */
    public function heatmap(): JsonResponse
    {
        $stationId = auth()->user()?->police_station_id;
        $data = $this->mapService->getHeatmapData($stationId);

        return response()->json($data);
    }

    /**
     * Reverse geocode coordinates using Nominatim
     */
    public function geocode(float $lat, float $lng): JsonResponse
    {
        $result = $this->mapService->reverseGeocode($lat, $lng);

        if ($result) {
            return response()->json($result);
        }

        return response()->json(['error' => 'Localização não encontrada'], 404);
    }

    /**
     * Get station coverage buffer (1km) as GeoJSON
     */
    public function stationCoverage(int $stationId): JsonResponse
    {
        $coverage = $this->mapService->getStationCoverage($stationId);

        if ($coverage) {
            return response()->json($coverage);
        }

        return response()->json(['error' => 'Cobertura não encontrada para este posto'], 404);
    }

    /**
     * Get all stations coverage buffers (1km) as GeoJSON
     */
    public function allStationsCoverage(): JsonResponse
    {
        $coverage = $this->mapService->getAllStationsCoverage();

        return response()->json($coverage);
    }

    /**
     * Get district boundary
     */
    public function districtBoundary(string $name): JsonResponse
    {
        $boundary = $this->mapService->getDistrictBoundary($name);

        if ($boundary) {
            return response()->json($boundary);
        }

        return response()->json(['error' => 'Distrito não encontrado'], 404);
    }
}
