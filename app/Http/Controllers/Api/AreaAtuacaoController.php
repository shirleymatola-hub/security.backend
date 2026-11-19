<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PoliceStation;
use App\Services\AreaAtuacaoService;
use App\Services\MapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AreaAtuacaoController
 *
 * Public endpoints expose the original "Area de Actuacao" polygons and their
 * spatial relations. The authenticated endpoint remains user-scoped: the
 * station/unit is derived from the authenticated user, never from request params.
 *
 * For squad_commander / manager / police:
 *   police_station → "Area de Actuacao".police_station_id
 *
 * For post_commander:
 *   unidade policial → unit_jurisdictions → bairros → "Area de Actuacao"
 *   filtered by the unit's police station where applicable.
 */
class AreaAtuacaoController extends Controller
{
    public function __construct(
        private readonly AreaAtuacaoService $areaAtuacaoService,
        private readonly MapService $mapService
    ) {}

    /**
     * All Area de Actuacao polygons as GeoJSON.
     */
    public function map(): JsonResponse
    {
        $geoJson = $this->areaAtuacaoService->geoJson();
        $bairros = array_values(array_unique(array_map(
            fn (array $feature) => $feature['properties']['bairro'],
            $geoJson['features']
        )));

        $geoJson['metadata'] = [
            'source_table' => 'Area de Actuacao',
            'total_areas' => count($geoJson['features']),
            'distinct_bairros' => count($bairros),
        ];

        return response()->json($geoJson);
    }

    /**
     * A single Area de Actuacao feature, including geometry and metadata.
     */
    public function show(int $area): JsonResponse
    {
        $feature = $this->areaAtuacaoService->find($area);

        if ($feature === null) {
            return response()->json(['error' => 'Área de actuação não encontrada.'], 404);
        }

        return response()->json($feature);
    }

    /**
     * Identify the Area(s) de Actuacao covering a coordinate.
     *
     * If more than one area covers the point (boundary/overlap), the response
     * is marked ambiguous and no police station is chosen arbitrarily.
     */
    public function locate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json(
            $this->areaAtuacaoService->findAtPoint((float) $data['lat'], (float) $data['lng'])
        );
    }

    /**
     * Public incidents inside an Area de Actuacao.
     *
     * The underlying MapService always filters is_public = true on public
     * endpoints, regardless of frontend parameters.
     */
    public function incidents(int $area): JsonResponse
    {
        $feature = $this->areaAtuacaoService->find($area);

        if ($feature === null) {
            return response()->json(['error' => 'Área de actuação não encontrada.'], 404);
        }

        $geoJson = $this->mapService->getIncidentsGeoJson(areaAtuacaoId: $area);
        $geoJson['area_de_atuacao'] = $feature['properties'];

        return response()->json($geoJson);
    }

    /**
     * Police units physically located inside an Area de Actuacao.
     *
     * This is a spatial report only; it does not change police_station_id.
     */
    public function units(int $area): JsonResponse
    {
        $feature = $this->areaAtuacaoService->find($area);

        if ($feature === null) {
            return response()->json(['error' => 'Área de actuação não encontrada.'], 404);
        }

        $unitFeatures = array_map(function (array $unit): array {
            return [
                'type' => 'Feature',
                'id' => $unit['id'],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$unit['longitude'], $unit['latitude']],
                ],
                'properties' => $unit,
            ];
        }, $feature['properties']['units'] ?? []);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $unitFeatures,
            'area_de_atuacao' => $feature['properties'],
        ]);
    }

    /**
     * Get the user's area de atuação as GeoJSON FeatureCollection.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        $stationId = $user->police_station_id;

        if (! $stationId) {
            return $this->buildResponse([], null, null);
        }

        $station = PoliceStation::find($stationId);

        if (! $station) {
            return $this->buildResponse([], null, null);
        }

        if ($user->hasRole('post_commander')) {
            return $this->getPostArea($station);
        }

        return $this->getStationArea($station);
    }

    /**
     * Get area for squad_commander / manager / police.
     */
    private function getStationArea(PoliceStation $station): JsonResponse
    {
        $geoJson = $this->areaAtuacaoService->geoJson([(int) $station->id]);

        return $this->buildResponse(
            $geoJson['features'],
            $station->latitude === null ? null : (float) $station->latitude,
            $station->longitude === null ? null : (float) $station->longitude,
            [],
            (int) $station->id
        );
    }

    /**
     * Get area for post_commander.
     *
     * Identification strategy:
     * 1. Find all units under this station whose physical bairro (via unidade_policial_bairros)
     *    contains the station's coordinates (ST_Covers).
     * 2. If exactly ONE unit matches → use it.
     * 3. If ZERO or MULTIPLE units match → return area_available=false.
     *    No fallback. No "first unit found". No distance heuristic.
     */
    private function getPostArea(PoliceStation $station): JsonResponse
    {
        $stationLat = $station->latitude === null ? null : (float) $station->latitude;
        $stationLng = $station->longitude === null ? null : (float) $station->longitude;

        $unitId = null;

        if ($stationLat && $stationLng) {
            $pointWKT = "SRID=4326;POINT({$stationLng} {$stationLat})";

            $matchingUnits = DB::select('
                SELECT up.id, up.unidade_policial
                FROM "unidades policiais" up
                INNER JOIN unidade_policial_bairros upb ON upb.unidade_policial_id = up.id
                INNER JOIN "Area de Estudo" ae ON ae.id = upb.area_estudo_id
                WHERE up.police_station_id = ?
                  AND ST_Covers(ae.geom, ?)
            ', [$station->id, $pointWKT]);

            if (count($matchingUnits) === 1) {
                $unitId = $matchingUnits[0]->id;
            } else {
                if (count($matchingUnits) > 1) {
                    $unitNames = array_map(fn ($u) => $u->unidade_policial, $matchingUnits);
                    Log::warning(
                        "post_commander unit ambiguity for user {$station->commander_id}: ".
                        count($matchingUnits).' units match station coordinates ['.
                        implode(', ', $unitNames).']'
                    );
                }

                return $this->buildResponse([], $stationLat, $stationLng, [
                    'area_available' => false,
                    'message' => 'Não foi possível determinar a unidade policial associada ao utilizador.',
                ], (int) $station->id);
            }
        }

        if (! $unitId) {
            return $this->buildResponse([], $stationLat, $stationLng, [
                'area_available' => false,
                'message' => 'Não foi possível determinar a unidade policial associada ao utilizador.',
            ], (int) $station->id);
        }

        $areaEstudoIds = DB::select('
            SELECT uj.area_estudo_id
            FROM unit_jurisdictions uj
            WHERE uj.unidade_policial_id = ?
              AND uj.is_active = true
              AND uj.area_estudo_id IS NOT NULL
        ', [$unitId]);

        $ids = array_values(array_unique(array_map(
            fn ($row) => (int) $row->area_estudo_id,
            $areaEstudoIds
        )));

        if (empty($ids)) {
            return $this->buildResponse([], $stationLat, $stationLng, [
                'area_available' => false,
                'message' => 'A unidade policial não possui jurisdições ativas.',
            ], (int) $station->id);
        }

        $bairros = DB::select(
            'SELECT DISTINCT "Bairro" AS bairro FROM "Area de Estudo" WHERE id IN ('
            .implode(',', array_fill(0, count($ids), '?'))
            .')',
            $ids
        );

        $bairroNames = array_values(array_filter(array_map(
            fn ($row) => $row->bairro,
            $bairros
        )));

        $geoJson = $this->areaAtuacaoService->geoJson([(int) $station->id], $bairroNames);

        return $this->buildResponse(
            $geoJson['features'],
            $stationLat,
            $stationLng,
            $geoJson['features'] === []
                ? [
                    'area_available' => false,
                    'message' => 'Não foi possível associar as jurisdições da unidade a uma área de actuação.',
                ]
                : [],
            (int) $station->id
        );
    }

    private function buildResponse(
        array $features,
        ?float $stationLat,
        ?float $stationLng,
        array $extra = [],
        ?int $stationId = null
    ): JsonResponse {
        $homeBairro = ($stationLat !== null && $stationLng !== null)
            ? $this->areaAtuacaoService->homeBairro($stationLat, $stationLng)
            : null;

        $detalhe = $extra['message'] ?? null;
        unset($extra['message']);

        $territorial = count($features) === 1
            ? $features[0]['properties']['responsabilidade_territorial']
            : [
                'estado' => $features === [] ? 'sem_area_operacional' : 'ambiguo',
                'areas' => array_map(
                    fn (array $feature) => $feature['properties']['responsabilidade_territorial'],
                    $features
                ),
            ];

        $avisos = count($features) === 1
            ? $features[0]['properties']['avisos']
            : ($features === [] ? [[
                'tipo' => 'sem_area_operacional',
                'mensagem' => 'Não existe uma Área de Actuação operacional associada a esta unidade policial.',
            ]] : []);

        $base = [
            'type' => 'FeatureCollection',
            'features' => $features,
            'home_bairro' => $homeBairro,
            'station_lat' => $stationLat,
            'station_lng' => $stationLng,
            'area_available' => count($features) > 0,
            'source_table' => 'Area de Actuacao',
            'area_status' => $features === [] ? 'sem_area_operacional' : 'area_operacional_disponivel',
            'responsabilidade_territorial' => $territorial,
            'tutela_administrativa' => $stationId === null
                ? ['estado' => 'sem_posto_identificado', 'fontes' => [], 'estacoes' => []]
                : $this->areaAtuacaoService->administrativeSourcesByStation($stationId),
            'avisos' => $avisos,
        ];

        if ($features === []) {
            $base['message'] = 'Não existe uma Área de Actuação operacional associada a esta unidade policial.';
        }

        if ($detalhe !== null) {
            $base['detalhe'] = $detalhe;
        }

        return response()->json(array_merge($base, $extra));
    }
}
