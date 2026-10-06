<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AreaDeEstudoController extends Controller
{
    /**
     * Display name overrides: maps bairro name => desired popup name.
     *
     * These override the raw name from unit_jurisdictions / jurisdictions
     * so the popup shows the parent esquadra name rather than the sub-unit.
     */
    private const DISPLAY_NAME_OVERRIDES = [
        'Fomento'      => '3ª Esquadra PRM Matola',
        'Liberdade'    => '4ª Esquadra da Liberdade',
        'Matola G'     => 'Posto Policial de Matola G',
        'Mussumbuluco' => 'Posto de Mussumbuluco',
    ];

    /**
     * Text shown when a bairro has multiple jurisdictions (territorial division).
     */
    private const DIVISAO_TERRITORIAL_TEXT = [
        'Matola D' => 'Avenida das Indústrias',
    ];

    /**
     * Clean a police station/unit name for display in popups.
     *
     * Strips the " - ..." suffix that some names carry (e.g.
     * "2ª Esquadra PRM Matola - Matola B / Cinema 700" → "2ª Esquadra PRM Matola").
     */
    private static function cleanDisplayName(string $name): string
    {
        $parts = explode(' - ', $name, 2);
        return $parts[0];
    }

    /**
     * Get all areas from "Area de Estudo" as GeoJSON FeatureCollection.
     *
     * Each Feature contains the bairro polygon geometry and relevant properties,
     * including the responsible police unit determined from existing
     * jurisdictions (primary) and unit_jurisdictions (fallback) relationships.
     */
    public function index(): JsonResponse
    {
        $rows = DB::select('SELECT * FROM "Area de Estudo" ORDER BY id');

        $unitJurRows = DB::select('
            SELECT uj.area_estudo_id, up.unidade_policial
            FROM unit_jurisdictions uj
            JOIN "unidades policiais" up ON up.id = uj.unidade_policial_id
            WHERE uj.is_active = true
        ');
        $unitJurMap = [];
        foreach ($unitJurRows as $uj) {
            if (!isset($unitJurMap[$uj->area_estudo_id])) {
                $unitJurMap[$uj->area_estudo_id] = [];
            }
            $unitJurMap[$uj->area_estudo_id][] = $uj->unidade_policial;
        }

        $jurRows = DB::select('
            SELECT j.area_estudo_id, ps.name
            FROM jurisdictions j
            JOIN police_stations ps ON ps.id = j.police_station_id
            WHERE j.is_active = true
        ');
        $jurMap = [];
        foreach ($jurRows as $j) {
            if (!isset($jurMap[$j->area_estudo_id])) {
                $jurMap[$j->area_estudo_id] = [];
            }
            $jurMap[$j->area_estudo_id][] = $j->name;
        }

        $features = [];
        $bairroJurisdictionMap = [];

        foreach ($rows as $row) {
            $geometry = null;

            if ($row->geom !== null) {
                $geojson = DB::selectOne(
                    'SELECT ST_AsGeoJSON(geom) AS geojson FROM "Area de Estudo" WHERE id = ?',
                    [$row->id]
                );

                if ($geojson && $geojson->geojson) {
                    $geometry = json_decode($geojson->geojson, true);
                }
            }

            if ($geometry === null) {
                continue;
            }

            $areaId = $row->id;
            $bairroName = $row->Bairro;

            $unitNames = $unitJurMap[$areaId] ?? [];
            $stationNames = $jurMap[$areaId] ?? [];

            $responsibleUnit = 'Não definida';

            if (count($stationNames) === 1) {
                $responsibleUnit = self::cleanDisplayName($stationNames[0]);
            } elseif (count($stationNames) > 1) {
                $responsibleUnit = 'Jurisdição parcial';
            } elseif (count($unitNames) === 1) {
                $responsibleUnit = self::cleanDisplayName($unitNames[0]);
            } elseif (count($unitNames) > 1) {
                $responsibleUnit = 'Jurisdição parcial';
            }

            $features[] = [
                'type' => 'Feature',
                'id' => $row->id,
                'geometry' => $geometry,
                'properties' => [
                    'id' => $row->id,
                    'cod_bairro' => $row->CodBairro,
                    'bairro' => $bairroName,
                    'cod_local' => $row->CodLocal,
                    'localidade' => $row->Localidade,
                    'cod_posto' => $row->CodPost,
                    'posto' => $row->Posto,
                    'cod_distrito' => $row->CodDist,
                    'distrito' => $row->Distrito,
                    'cod_provincia' => $row->CodProv,
                    'provincia' => $row->Provincia,
                    'unidade_policial_responsavel' => $responsibleUnit,
                ],
            ];

            if (!isset($bairroJurisdictionMap[$bairroName])) {
                $bairroJurisdictionMap[$bairroName] = [];
            }
            $bairroJurisdictionMap[$bairroName][] = count($features) - 1;
        }

        foreach ($bairroJurisdictionMap as $bairroName => $indices) {
            if (count($indices) <= 1) {
                continue;
            }

            $stationName = null;
            foreach ($indices as $idx) {
                $areaIdForJur = $features[$idx]['properties']['id'];
                $stationNames = $jurMap[$areaIdForJur] ?? [];
                if (count($stationNames) === 1) {
                    $stationName = self::cleanDisplayName($stationNames[0]);
                    break;
                }
            }

            if ($stationName !== null) {
                foreach ($indices as $idx) {
                    $features[$idx]['properties']['unidade_policial_responsavel'] = $stationName;
                }
            }
        }

        foreach ($features as &$feature) {
            $props = &$feature['properties'];
            $bairroName = $props['bairro'];
            $areaId = $props['id'];

            if (isset(self::DISPLAY_NAME_OVERRIDES[$bairroName])) {
                $props['unidade_policial_responsavel'] = self::DISPLAY_NAME_OVERRIDES[$bairroName];
                unset($props);
                continue;
            }

            $stationNames = $jurMap[$areaId] ?? [];
            if (count($stationNames) > 1 && isset(self::DIVISAO_TERRITORIAL_TEXT[$bairroName])) {
                $unitNames = [];
                foreach ($stationNames as $sn) {
                    $unitNames[] = self::cleanDisplayName($sn);
                }
                $unitNames = array_values(array_unique($unitNames));

                $props['unidade_policial_responsavel'] = implode(' / ', $unitNames);
                $props['unidades_policiais_responsaveis'] = $unitNames;
                $props['divisao_territorial'] = self::DIVISAO_TERRITORIAL_TEXT[$bairroName];
            }

            unset($props);
        }
        unset($feature);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
