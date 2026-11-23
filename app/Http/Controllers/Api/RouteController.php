<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AreaAtuacaoService;
use App\Services\IncidentJurisdictionService;
use App\Services\OpenRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\PoliceStation;

class RouteController extends Controller
{
    public function __construct(
        protected OpenRouteService $routeService,
        protected AreaAtuacaoService $areaAtuacaoService
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
            $message = $e->getMessage();
            $status = 502;

            if ($message === 'Serviço de roteamento não configurado.' || $message === 'Serviço de roteamento indisponível.') {
                $status = 503;
            } elseif ($message === 'Não foi possível determinar um percurso entre os pontos indicados.') {
                $status = 422;
            }

            return response()->json([
                'error' => 'Erro ao calcular rota',
                'message' => $message,
            ], $status);
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
        $result = $jurisdictions->determinePoliceStationFromGeometry($lng, $lat);
        $responsibleIds = $this->responsibleStationIds($result);

        return response()->json([
            'stations' => $stations->map(fn($s) => $this->formatStation($s) + [
                'is_responsible' => in_array($s->id, $responsibleIds, true),
            ])->values(),
            'responsible_station_ids' => $responsibleIds,
            'query' => ['lat' => $lat, 'lng' => $lng],
            'responsabilidade_territorial' => $this->areaAtuacaoService->findAtPoint($lat, $lng)['responsabilidade_territorial'],
            'tutela_administrativa' => $this->administrativeFromJurisdictionResult($result),
        ]);
    }

    /**
     * Esquadra responsável por um ponto segundo a área de jurisdição (bairro -> jurisdição ativa).
     *
     * basis:
     *   - jurisdiction           -> o bairro tem uma única jurisdição ativa;
     *   - jurisdiction_conflict  -> o bairro tem várias jurisdições (ex.: Matola D): devolve a mais
     *                              próxima dessas esquadras e as restantes em `alternatives`;
     *   - nearest                -> ponto fora da Área de Estudo ou bairro sem jurisdição: usa a
     *                              esquadra ativa mais próxima (informação de apoio, sem atribuição
     *                              automática de responsabilidade territorial).
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
            'responsabilidade_territorial' => $this->areaAtuacaoService->findAtPoint($lat, $lng)['responsabilidade_territorial'],
            'tutela_administrativa' => $this->administrativeFromJurisdictionResult($result),
        ]);
    }

    private function administrativeFromJurisdictionResult(array $result): array
    {
        $rawStations = [];

        if ($result['success']) {
            $rawStations[] = [
                'source_id' => $result['jurisdiction_id'] ?? null,
                'police_station_id' => $result['police_station_id'],
                'police_station_name' => $result['station_name'],
            ];
        }

        if ($result['conflict']) {
            foreach ($result['multiple_jurisdictions'] as $jurisdiction) {
                $rawStations[] = [
                    'source_id' => $jurisdiction['jurisdiction_id'] ?? null,
                    'police_station_id' => $jurisdiction['police_station_id'] ?? null,
                    'police_station_name' => $jurisdiction['station_name'] ?? null,
                ];
            }
        }

        $estacoes = [];
        foreach ($rawStations as $station) {
            if ($station['police_station_id'] === null) {
                continue;
            }
            $id = (int) $station['police_station_id'];
            $estacoes[$id] = [
                'police_station_id' => $id,
                'police_station_name' => $station['police_station_name'],
            ];
        }

        $estacoes = array_values($estacoes);
        usort($estacoes, fn (array $left, array $right) => $left['police_station_id'] <=> $right['police_station_id']);

        return [
            'estado' => $result['success']
                ? 'definida'
                : ($result['conflict'] ? 'divergente' : 'sem_registo_administrativo'),
            'fontes' => [[
                'fonte' => 'jurisdictions',
                'registos' => array_map(static fn (array $station) => [
                    'source_id' => $station['source_id'] ?? null,
                    'police_station_id' => $station['police_station_id'],
                    'police_station_name' => $station['police_station_name'],
                ], $rawStations),
            ]],
            'estacoes' => $estacoes,
        ];
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