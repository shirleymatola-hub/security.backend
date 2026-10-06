<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PoliceStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AreaAtuacaoController
 *
 * Returns the GeoJSON "Área de atuação" for the authenticated user.
 * The station/unit is derived from the authenticated user — never from request params.
 *
 * For squad_commander / manager / police:
 *   police_station → jurisdictions → Area de Estudo
 *
 * For post_commander:
 *   unidade policial → unit_jurisdictions → Area de Estudo
 */
class AreaAtuacaoController extends Controller
{
    /**
     * Get the user's area de atuação as GeoJSON FeatureCollection.
     *
     * Response includes:
     * - features: bairro polygons from "Area de Estudo"
     * - home_bairro: name of the bairro containing the station/unit
     * - station_lat / station_lng: coordinates of the station/unit
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        $stationId = $user->police_station_id;

        if (!$stationId) {
            return response()->json([
                'type' => 'FeatureCollection',
                'features' => [],
                'home_bairro' => null,
                'station_lat' => null,
                'station_lng' => null,
            ]);
        }

        $station = PoliceStation::find($stationId);

        if (!$station) {
            return response()->json([
                'type' => 'FeatureCollection',
                'features' => [],
                'home_bairro' => null,
                'station_lat' => null,
                'station_lng' => null,
            ]);
        }

        if ($user->hasRole('post_commander')) {
            return $this->getPostArea($station);
        }

        return $this->getStationArea($station);
    }

    /**
     * Get area for squad_commander / manager / police.
     *
     * Uses: police_station → jurisdictions (is_active) → Area de Estudo
     */
    private function getStationArea(PoliceStation $station): JsonResponse
    {
        $jurisdictions = $station->jurisdictions()
            ->where('jurisdictions.is_active', true)
            ->with('areaDeEstudo')
            ->get();

        return $this->buildGeoJsonResponse(
            $jurisdictions->pluck('areaDeEstudo')->filter(),
            $station->latitude,
            $station->longitude
        );
    }

    /**
     * Get area for post_commander.
     *
     * Uses: unidade policial (under this station) → unit_jurisdictions (is_active) → Area de Estudo
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
        $stationLat = $station->latitude;
        $stationLng = $station->longitude;

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
                    $unitNames = array_map(fn($u) => $u->unidade_policial, $matchingUnits);
                    \Illuminate\Support\Facades\Log::warning(
                        "post_commander unit ambiguity for user {$station->commander_id}: " .
                        count($matchingUnits) . ' units match station coordinates [' .
                        implode(', ', $unitNames) . ']'
                    );
                }

                return response()->json([
                    'type' => 'FeatureCollection',
                    'features' => [],
                    'home_bairro' => null,
                    'station_lat' => $stationLat,
                    'station_lng' => $stationLng,
                    'area_available' => false,
                    'message' => 'Não foi possível determinar a unidade policial associada ao utilizador.',
                ]);
            }
        }

        if (!$unitId) {
            return response()->json([
                'type' => 'FeatureCollection',
                'features' => [],
                'home_bairro' => null,
                'station_lat' => $stationLat,
                'station_lng' => $stationLng,
                'area_available' => false,
                'message' => 'Não foi possível determinar a unidade policial associada ao utilizador.',
            ]);
        }

        $areaEstudoIds = DB::select('
            SELECT uj.area_estudo_id
            FROM unit_jurisdictions uj
            WHERE uj.unidade_policial_id = ?
              AND uj.is_active = true
              AND uj.area_estudo_id IS NOT NULL
        ', [$unitId]);

        $ids = array_column($areaEstudoIds, 'area_estudo_id');

        if (empty($ids)) {
            return response()->json([
                'type' => 'FeatureCollection',
                'features' => [],
                'home_bairro' => null,
                'station_lat' => $stationLat,
                'station_lng' => $stationLng,
                'area_available' => false,
                'message' => 'A unidade policial não possui jurisdições ativas.',
            ]);
        }

        $bairros = DB::select(
            'SELECT id, "Bairro" AS bairro, "CodBairro" AS cod_bairro FROM "Area de Estudo" WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        );

        return $this->buildGeoJsonResponse(
            collect($bairros),
            $stationLat,
            $stationLng
        );
    }

    /**
     * Build a GeoJSON FeatureCollection from a collection of bairro records.
     *
     * Also determines the home_bairro (bairro containing the station coordinates).
     */
    private function buildGeoJsonResponse($bairros, ?float $stationLat, ?float $stationLng): JsonResponse
    {
        $features = [];
        $homeBairro = null;

        foreach ($bairros as $bairro) {
            $bairroId = is_object($bairro) ? ($bairro->id ?? null) : null;
            $bairroName = is_object($bairro) ? ($bairro->bairro ?? null) : null;

            if (!$bairroId || !$bairroName) {
                continue;
            }

            $geojson = DB::selectOne(
                'SELECT ST_AsGeoJSON(geom) AS geojson FROM "Area de Estudo" WHERE id = ?',
                [$bairroId]
            );

            if (!$geojson || !$geojson->geojson) {
                continue;
            }

            $geometry = json_decode($geojson->geojson, true);

            $features[] = [
                'type' => 'Feature',
                'id' => $bairroId,
                'geometry' => $geometry,
                'properties' => [
                    'id' => $bairroId,
                    'bairro' => $bairroName,
                    'cod_bairro' => is_object($bairro) ? ($bairro->cod_bairro ?? null) : null,
                ],
            ];
        }

        if ($stationLat && $stationLng && !empty($features)) {
            $pointWKT = "SRID=4326;POINT({$stationLng} {$stationLat})";

            $home = DB::selectOne(
                'SELECT "Bairro" AS bairro FROM "Area de Estudo" WHERE ST_Covers(geom, ?) LIMIT 1',
                [$pointWKT]
            );

            if ($home) {
                $homeBairro = $home->bairro;
            }
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
            'home_bairro' => $homeBairro,
            'station_lat' => $stationLat,
            'station_lng' => $stationLng,
        ]);
    }
}
