<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IncidentJurisdictionService;
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
    public function nearestStation(Request $request, IncidentJurisdictionService $jurisdictions)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'exclude_station_id' => 'nullable|integer',
        ]);

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $excludeId = $request->input('exclude_station_id');

        $query = $this->stationsByDistance($lat, $lng)->withinStudyArea();

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $stations = $query->orderBy('distance')
            ->limit(5)
            ->get();

        // Marca a(s) esquadra(s) com jurisdição sobre o ponto, para a interface as destacar.
        $responsibleIds = $this->responsibleStationIds(
            $jurisdictions->determinePoliceStationFromGeometry($lng, $lat)
        );

        return response()->json([
            'stations' => $stations->map(fn($s) => $this->formatStation($s) + [
                'is_responsible' => in_array($s->id, $responsibleIds, true),
            ])->values(),
            'responsible_station_ids' => $responsibleIds,
            'query' => ['lat' => $lat, 'lng' => $lng],
        ]);
    }

    /**
     * Esquadra responsável por um ponto segundo a área de jurisdição (bairro → jurisdição ativa).
     *
     * basis:
     *   - jurisdiction           → o bairro tem uma única jurisdição ativa;
     *   - jurisdiction_conflict  → o bairro tem várias jurisdições (ex.: Matola D): devolve a mais
     *                              próxima dessas esquadras e as restantes em `alternatives`;
     *   - nearest                → ponto fora da Área de Estudo ou bairro sem jurisdição: usa a
     *                              esquadra ativa mais próxima.
     */
    public function responsibleStation(Request $request, IncidentJurisdictionService $jurisdictions): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;

        $result = $jurisdictions->determinePoliceStationFromGeometry($lng, $lat);
        $candidateIds = $this->responsibleStationIds($result);

        $stations = collect();
        if ($candidateIds) {
            $stations = $this->stationsByDistance($lat, $lng)
                ->whereIn('id', $candidateIds)
                ->orderBy('distance')
                ->get();
        }

        if ($stations->isNotEmpty()) {
            $basis = $result['conflict'] ? 'jurisdiction_conflict' : 'jurisdiction';
        } else {
            $basis = 'nearest';
            $stations = $this->stationsByDistance($lat, $lng)
                ->withinStudyArea()
                ->orderBy('distance')
                ->limit(3)
                ->get();
        }

        if ($stations->isEmpty()) {
            return response()->json(['message' => __('Nenhuma esquadra encontrada')], 404);
        }

        $station = $stations->first();
        $messages = [
            'jurisdiction' => __('Esquadra responsável pela área de jurisdição do bairro :bairro.', ['bairro' => $result['bairro']]),
            'jurisdiction_conflict' => __('O bairro :bairro tem mais de uma esquadra com jurisdição; foi escolhida a mais próxima.', ['bairro' => $result['bairro']]),
            'nearest' => __('O local não tem jurisdição definida; foi escolhida a esquadra mais próxima.'),
        ];

        return response()->json([
            'station' => $this->formatStation($station),
            'alternatives' => $stations->slice(1)->map(fn($s) => $this->formatStation($s))->values(),
            'basis' => $basis,
            'bairro' => $result['bairro'],
            'message' => $messages[$basis],
        ]);
    }

    /** Esquadras ativas com a distância (m) ao ponto indicado. */
    private function stationsByDistance(float $lat, float $lng)
    {
        return PoliceStation::where('is_active', true)
            ->select('*')
            ->selectRaw(
                "ST_Distance(
                    geometry::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) AS distance",
                [$lng, $lat]
            );
    }

    /** IDs das esquadras com jurisdição, a partir do resultado do IncidentJurisdictionService. */
    private function responsibleStationIds(array $result): array
    {
        if ($result['success']) {
            return [(int) $result['police_station_id']];
        }

        if ($result['conflict']) {
            return collect($result['multiple_jurisdictions'])
                ->pluck('police_station_id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }

    private function formatStation(PoliceStation $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'address' => $s->address,
            'phone' => $s->phone,
            'latitude' => $s->latitude,
            'longitude' => $s->longitude,
            'distance' => round($s->distance, 2),
        ];
    }
}