<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MapService;
use App\Services\PoliceJurisdictionSpatialFilter;
use App\Services\SpatialAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function __construct(
        protected MapService $mapService,
        protected SpatialAggregationService $aggregationService
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
        $withinAreaDeEstudo = $request->boolean('area_de_estudo');
        $areaEstudoId = $request->filled('area_de_estudo_id') ? $request->integer('area_de_estudo_id') : null;
        $areaAtuacaoId = $request->filled('area_de_atuacao') ? $request->integer('area_de_atuacao') : null;
        $jurisdictionStationId = $this->jurisdictionStationId($request);

        if ($jurisdictionStationId !== null) {
            $filters['jurisdiction_station_id'] = $jurisdictionStationId;
        }

        $geoJson = $this->mapService->getIncidentsGeoJson(
            null,
            $filters,
            null,
            $withinAreaDeEstudo,
            $areaAtuacaoId,
            $areaEstudoId,
            $jurisdictionStationId
        );

        $requested = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('aggregate', ''))
        )));

        if (in_array('all', $requested, true)) {
            $requested = ['bairro', 'jurisdicao', 'area_atuacao'];
        }

        if ($requested !== []) {
            $geoJson['aggregations'] = $this->aggregationService->aggregate($requested, $filters);
        }

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
        $withinAreaDeEstudo = $request->boolean('area_de_estudo');
        $geoJson = $this->mapService->getStationsGeoJson(null, $mainOnly, $withinAreaDeEstudo);

        return response()->json($geoJson);
    }

    /**
     * Get SERNIC unit as GeoJSON for the map layer.
     * Returns only police stations with tipo_posto = 'Investigação Criminal'.
     */
    public function sernic(Request $request): JsonResponse
    {
        $withinAreaDeEstudo = $request->boolean('area_de_estudo');
        $geoJson = $this->mapService->getSernicGeoJson($withinAreaDeEstudo);

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

    private function jurisdictionStationId(Request $request): ?int
    {
        if ($request->filled('jurisdiction_station_id')) {
            $stationId = $request->integer('jurisdiction_station_id');

            return $stationId > 0 ? $stationId : null;
        }

        if ($request->filled('jurisdiction_id')) {
            $jurisdictionId = $request->integer('jurisdiction_id');

            return $jurisdictionId > 0
                ? PoliceJurisdictionSpatialFilter::stationIdForJurisdiction($jurisdictionId)
                : null;
        }

        return null;
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
