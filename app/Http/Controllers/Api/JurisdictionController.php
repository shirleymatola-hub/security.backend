<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Jurisdiction;
use App\Models\PoliceStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class JurisdictionController extends Controller
{
    /**
     * Clean a police station name for display in popups.
     * Strips the " - ..." suffix (e.g.
     * "2ª Esquadra PRM Matola - Matola B / Cinema 700" → "2ª Esquadra PRM Matola").
     */
    private static function cleanDisplayName(string $name): string
    {
        $parts = explode(' - ', $name, 2);
        return $parts[0];
    }

    /**
     * Get all jurisdictions as GeoJSON for the map layer.
     * Returns bairros from "Area de Estudo", merging multiple esquadras per bairro.
     */
    public function index(): JsonResponse
    {
        $jurisdictions = Jurisdiction::with(['policeStation', 'areaDeEstudo'])
            ->where('is_active', true)
            ->get();

        $bairroMap = [];

        foreach ($jurisdictions as $j) {
            if (!$j->areaDeEstudo) {
                continue;
            }

            $bairroName = $j->areaDeEstudo->Bairro;

            if (!isset($bairroMap[$bairroName])) {
                $geometry = DB::selectOne(
                    'SELECT ST_AsGeoJSON(geom) AS geojson FROM "Area de Estudo" WHERE id = ?',
                    [$j->area_estudo_id]
                );

                if (!$geometry || !$geometry->geojson) {
                    continue;
                }

                $bairroMap[$bairroName] = [
                    'geometry' => json_decode($geometry->geojson, true),
                    'cod_bairro' => $j->areaDeEstudo->CodBairro,
                    'esquadras' => [],
                ];
            }

            $cleanName = self::cleanDisplayName($j->policeStation?->name ?? '');
            if (!in_array($cleanName, $bairroMap[$bairroName]['esquadras'])) {
                $bairroMap[$bairroName]['esquadras'][] = $cleanName;
            }
        }

        $features = [];
        foreach ($bairroMap as $bairroName => $data) {
            $esquadras = $data['esquadras'];
            sort($esquadras);
            $features[] = [
                'type' => 'Feature',
                'id' => $bairroName,
                'geometry' => $data['geometry'],
                'properties' => [
                    'bairro' => $bairroName,
                    'cod_bairro' => $data['cod_bairro'],
                    'police_station_name' => implode(' / ', $esquadras),
                    'esquadras' => $esquadras,
                ],
            ];
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Get jurisdictions for a specific police station.
     */
    public function byStation(int $stationId): JsonResponse
    {
        $jurisdictions = Jurisdiction::with(['areaDeEstudo'])
            ->where('police_station_id', $stationId)
            ->where('is_active', true)
            ->get();

        $features = [];

        foreach ($jurisdictions as $j) {
            if (!$j->areaDeEstudo) {
                continue;
            }

            $geometry = DB::selectOne(
                'SELECT ST_AsGeoJSON(geom) AS geojson FROM "Area de Estudo" WHERE id = ?',
                [$j->area_estudo_id]
            );

            if (!$geometry || !$geometry->geojson) {
                continue;
            }

            $geometry = json_decode($geometry->geojson, true);

            $features[] = [
                'type' => 'Feature',
                'id' => $j->id,
                'geometry' => $geometry,
                'properties' => [
                    'jurisdiction_id' => $j->id,
                    'police_station_id' => $j->police_station_id,
                    'police_station_name' => self::cleanDisplayName($j->policeStation?->name ?? ''),
                    'area_estudo_id' => $j->area_estudo_id,
                    'bairro' => $j->areaDeEstudo->Bairro,
                    'cod_bairro' => $j->areaDeEstudo->CodBairro,
                ],
            ];
        }

        $geoJson = [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];

        return response()->json($geoJson);
    }

    /**
     * Get summary of jurisdictions grouped by esquadra.
     */
    public function summary(): JsonResponse
    {
        $stations = PoliceStation::whereIn('id', [2, 7, 10, 11])
            ->with(['jurisdictions.areaDeEstudo'])
            ->get();

        $result = [];

        foreach ($stations as $station) {
            $bairros = $station->jurisdictions
                ->filter(fn($j) => $j->is_active && $j->areaDeEstudo)
                ->map(fn($j) => [
                    'bairro' => $j->areaDeEstudo->Bairro,
                    'is_partial' => str_contains($j->name ?? '', 'parcial'),
                    'description' => $j->description,
                ])
                ->values();

            $result[] = [
                'police_station_id' => $station->id,
                'name' => $station->name,
                'code' => $station->code,
                'bairro' => $station->bairro,
                'bairros' => $bairros,
            ];
        }

        return response()->json($result);
    }
}
