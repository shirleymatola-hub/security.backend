<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UnidadePolicia;
use App\Models\UnitJurisdiction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PoliceUnitController extends Controller
{
    /**
     * List all police subunits (unidades policiais) as GeoJSON for the map layer.
     *
     * Excludes units that are exact coordinate duplicates of their parent
     * esquadra (same latitude, longitude, and police_station_id) to avoid
     * overlapping markers with the "Esquadras Policiais" layer.
     *
     * Each feature includes:
     * - Physical location (latitude/longitude)
     * - Tutelage station (police_station_id)
     * - Physical bairro (via unidade_policial_bairros)
     */
    public function index(): JsonResponse
    {
        $units = UnidadePolicia::with(['policeStation', 'unidadePolicialBairros.areaDeEstudo'])
            ->get();

        $features = [];

        foreach ($units as $unit) {
            if ($unit->latitude === null || $unit->longitude === null) {
                continue;
            }

            if ($unit->policeStation
                && (float) $unit->latitude === (float) $unit->policeStation->latitude
                && (float) $unit->longitude === (float) $unit->policeStation->longitude
            ) {
                continue;
            }

            $physicalBairros = $unit->unidadePolicialBairros
                ->pluck('areaDeEstudo.Bairro')
                ->filter()
                ->values()
                ->toArray();

            $features[] = [
                'type' => 'Feature',
                'id' => $unit->id,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        (float) $unit->longitude,
                        (float) $unit->latitude,
                    ],
                ],
                'properties' => [
                    'id' => $unit->id,
                    'unidade_policial' => $unit->unidade_policial,
                    'esquadra_tutela' => $unit->esquadra_tutela,
                    'police_station_id' => $unit->police_station_id,
                    'police_station_name' => $unit->policeStation?->name,
                    'localizacao_referencia' => $unit->localizacao_referencia,
                    'area_jurisdicao' => $unit->area_jurisdicao,
                    'contacto_telefonico' => $unit->contacto_telefonico,
                    'latitude' => (float) $unit->latitude,
                    'longitude' => (float) $unit->longitude,
                    'physical_bairros' => $physicalBairros,
                ],
            ];
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * List all active unit jurisdictions as GeoJSON for the map layer.
     *
     * Geometry is sourced from "Area de Estudo".geom via area_estudo_id,
     * NOT from unit_jurisdictions.geometry (which is not populated).
     */
    public function jurisdictions(): JsonResponse
    {
        $unitJurisdictions = UnitJurisdiction::with(['unidadePolicia.policeStation', 'areaDeEstudo'])
            ->where('is_active', true)
            ->get();

        $features = [];

        foreach ($unitJurisdictions as $uj) {
            if (!$uj->areaDeEstudo) {
                continue;
            }

            $geometry = DB::selectOne(
                'SELECT ST_AsGeoJSON(geom) AS geojson FROM "Area de Estudo" WHERE id = ?',
                [$uj->area_estudo_id]
            );

            if (!$geometry || !$geometry->geojson) {
                continue;
            }

            $geometry = json_decode($geometry->geojson, true);

            $features[] = [
                'type' => 'Feature',
                'id' => $uj->id,
                'geometry' => $geometry,
                'properties' => [
                    'unit_jurisdiction_id' => $uj->id,
                    'unidade_policial_id' => $uj->unidade_policial_id,
                    'unidade_policial_nome' => $uj->unidadePolicia?->unidade_policial,
                    'police_station_id' => $uj->unidadePolicia?->police_station_id,
                    'police_station_name' => $uj->unidadePolicia?->policeStation?->name,
                    'area_estudo_id' => $uj->area_estudo_id,
                    'bairro' => $uj->areaDeEstudo->Bairro,
                    'cod_bairro' => $uj->areaDeEstudo->CodBairro,
                    'name' => $uj->name,
                    'description' => $uj->description,
                    'effective_date' => $uj->effective_date?->format('Y-m-d'),
                    'source_document' => $uj->source_document,
                    'is_active' => $uj->is_active,
                ],
            ];
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
