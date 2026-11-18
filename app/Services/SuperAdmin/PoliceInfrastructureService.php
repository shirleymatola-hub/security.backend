<?php

namespace App\Services\SuperAdmin;

use App\Models\PoliceStation;
use App\Models\UnidadePolicia;
use App\Services\AreaDeEstudoSpatialFilter;

class PoliceInfrastructureService
{
    public const TIPO_ESQUADRA = 'esquadra';
    public const TIPO_POSTO = 'posto';
    public const TIPO_SERNIC = 'sernic';

    private const TIPO_POSTO_ESQUADRA = 'Esquadra da PRM';
    private const TIPO_POSTO_SERNIC = 'Investigação Criminal';

    public function features(bool $onlyStudyArea = false): array
    {
        $query = UnidadePolicia::with(['policeStation', 'unidadePolicialBairros.areaDeEstudo']);

        if ($onlyStudyArea) {
            AreaDeEstudoSpatialFilter::apply($query, 'geom');
        }

        $features = [];
        $seen = [];

        foreach ($query->get() as $unit) {
            if ($unit->latitude === null || $unit->longitude === null) {
                continue;
            }

            $station = $unit->policeStation;
            $tipoPosto = $station?->tipo_posto;
            $name = trim((string) $unit->unidade_policial);
            $type = $this->classify($tipoPosto, $name, $unit, $station);

            $key = $this->dedupKey($type, (float) $unit->latitude, (float) $unit->longitude);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $features[] = [
                'type' => 'Feature',
                'id' => 'unidade-'.$unit->id,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $unit->longitude, (float) $unit->latitude],
                ],
                'properties' => $this->properties([
                    'id' => 'unidade-'.$unit->id,
                    'unidade_policial' => $unit->unidade_policial,
                    'tipo_unidade' => $type,
                    'esquadra_tutela' => $unit->esquadra_tutela,
                    'police_station_id' => $unit->police_station_id,
                    'police_station_name' => $station?->name,
                    'localizacao_referencia' => $unit->localizacao_referencia,
                    'area_jurisdicao' => $unit->area_jurisdicao,
                    'contacto_telefonico' => $unit->contacto_telefonico,
                    'latitude' => (float) $unit->latitude,
                    'longitude' => (float) $unit->longitude,
                    'physical_bairros' => $unit->unidadePolicialBairros
                        ->pluck('areaDeEstudo.Bairro')
                        ->filter()
                        ->values()
                        ->toArray(),
                    'source' => 'unidade',
                ]),
            ];
        }

        $stationsQuery = PoliceStation::query()
            ->where('tipo_posto', self::TIPO_POSTO_SERNIC)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($onlyStudyArea) {
            AreaDeEstudoSpatialFilter::apply($stationsQuery);
        }

        foreach ($stationsQuery->get() as $station) {
            $key = $this->dedupKey(self::TIPO_SERNIC, (float) $station->latitude, (float) $station->longitude);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $features[] = [
                'type' => 'Feature',
                'id' => 'estacao-'.$station->id,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $station->longitude, (float) $station->latitude],
                ],
                'properties' => $this->properties([
                    'id' => 'estacao-'.$station->id,
                    'unidade_policial' => $station->name,
                    'tipo_unidade' => self::TIPO_SERNIC,
                    'esquadra_tutela' => $station->name,
                    'police_station_id' => $station->id,
                    'police_station_name' => $station->name,
                    'localizacao_referencia' => $station->address,
                    'area_jurisdicao' => null,
                    'contacto_telefonico' => $station->phone,
                    'latitude' => (float) $station->latitude,
                    'longitude' => (float) $station->longitude,
                    'physical_bairros' => [],
                    'source' => 'estacao',
                ]),
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'metadata' => [
                'total' => count($features),
                'esquadras' => $this->countByType($features, self::TIPO_ESQUADRA),
                'postos' => $this->countByType($features, self::TIPO_POSTO),
                'sernic' => $this->countByType($features, self::TIPO_SERNIC),
                'source_table' => 'unidades policiais + police_stations',
                'within_study_area' => $onlyStudyArea,
            ],
        ];
    }

    public function countWithinStudyArea(): int
    {
        return count($this->features(true)['features']);
    }

    private function classify(?string $tipoPosto, string $name, UnidadePolicia $unit, ?PoliceStation $station): string
    {
        if ($tipoPosto === self::TIPO_POSTO_SERNIC || stripos($name, 'sernic') !== false) {
            return self::TIPO_SERNIC;
        }

        if ($tipoPosto !== self::TIPO_POSTO_ESQUADRA) {
            return self::TIPO_POSTO;
        }

        if ($this->coincidesWithStation($unit, $station)) {
            return self::TIPO_ESQUADRA;
        }

        return preg_match('/^\s*esquadra\b/i', $name) === 1 ? self::TIPO_ESQUADRA : self::TIPO_POSTO;
    }

    private function coincidesWithStation(UnidadePolicia $unit, ?PoliceStation $station): bool
    {
        if (! $station || $station->latitude === null || $station->longitude === null) {
            return false;
        }

        return round((float) $unit->latitude, 6) === round((float) $station->latitude, 6)
            && round((float) $unit->longitude, 6) === round((float) $station->longitude, 6);
    }

    private function dedupKey(string $type, float $latitude, float $longitude): string
    {
        return $type.'|'.round($latitude, 6).'|'.round($longitude, 6);
    }

    private function countByType(array $features, string $type): int
    {
        return count(array_filter($features, fn (array $feature) => ($feature['properties']['tipo_unidade'] ?? null) === $type));
    }

    private function properties(array $properties): array
    {
        $labels = [
            self::TIPO_ESQUADRA => 'Esquadra Policial',
            self::TIPO_POSTO => 'Posto/Subunidade',
            self::TIPO_SERNIC => 'SERNIC',
        ];

        return $properties + ['tipo_unidade_label' => $labels[$properties['tipo_unidade']] ?? 'Unidade Policial'];
    }
}
