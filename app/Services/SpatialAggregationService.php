<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class SpatialAggregationService
{
    private const SUPPORTED = ['bairro', 'jurisdicao', 'area_atuacao'];

    /** @param array<string, mixed> $filters */
    public function aggregate(array $dimensions, array $filters = []): array
    {
        $result = [];

        foreach ($dimensions as $dimension) {
            if (in_array($dimension, self::SUPPORTED, true)) {
                $result[$dimension] = match ($dimension) {
                    'bairro' => $this->byBairro($filters),
                    'jurisdicao' => $this->byJurisdicao($filters),
                    'area_atuacao' => $this->byAreaAtuacao($filters),
                };
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $filters */
    public function byBairro(array $filters = []): array
    {
        [$where, $bindings] = $this->baseWhere($filters);

        $rows = DB::select($this->locatedCte($where).'
            SELECT bairro, count(*)::int AS total
            FROM located
            GROUP BY bairro
            ORDER BY total DESC, bairro ASC
        ', $bindings);

        return array_map(static fn ($row): array => [
            'bairro' => (string) $row->bairro,
            'total' => (int) $row->total,
        ], $rows);
    }

    /** @param array<string, mixed> $filters */
    public function byJurisdicao(array $filters = []): array
    {
        [$where, $bindings] = $this->baseWhere($filters);

        $rows = DB::select($this->locatedCte($where).',
            territory_matches AS (
                SELECT DISTINCT ON (l.id, aa.police_station_id)
                    l.id,
                    aa.id AS area_atuacao_id,
                    aa.police_station_id AS area_station_id,
                    aaps.name AS area_station_name
                FROM located l
                JOIN "Area de Actuacao" aa
                  ON aa.geom IS NOT NULL
                 AND ST_IsValid(aa.geom)
                 AND ST_SRID(aa.geom) > 0
                 AND ST_Covers(
                     ST_Transform(aa.geom, 4326),
                     ST_SetSRID(ST_MakePoint(l.longitude::double precision, l.latitude::double precision), 4326)
                 )
                LEFT JOIN police_stations aaps ON aaps.id = aa.police_station_id
                ORDER BY l.id, aa.police_station_id, aa.id
            ),
            active_jurisdictions AS (
                SELECT DISTINCT
                    l.id,
                    j.id AS jurisdiction_id,
                    j.police_station_id AS jurisdiction_station_id,
                    jps.name AS jurisdiction_station_name
                FROM located l
                JOIN jurisdictions j
                  ON j.area_estudo_id = l.area_estudo_id
                 AND j.is_active = true
                LEFT JOIN police_stations jps ON jps.id = j.police_station_id
            )
            SELECT l.id, l.bairro,
                   tm.area_atuacao_id, tm.area_station_id, tm.area_station_name,
                   aj.jurisdiction_id, aj.jurisdiction_station_id, aj.jurisdiction_station_name
            FROM located l
            LEFT JOIN territory_matches tm ON tm.id = l.id
            LEFT JOIN active_jurisdictions aj ON aj.id = l.id
            ORDER BY l.id, tm.area_atuacao_id, aj.jurisdiction_id
        ', $bindings);

        $incidents = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $incidents[$id] ??= [
                'id' => $id,
                'bairro' => (string) $row->bairro,
                'area_stations' => [],
                'area_atuacao_ids' => [],
                'jurisdictions' => [],
            ];

            if ($row->area_station_id !== null) {
                $stationId = (int) $row->area_station_id;
                $incidents[$id]['area_stations'][$stationId] = (string) $row->area_station_name;
                $incidents[$id]['area_atuacao_ids'][(int) $row->area_atuacao_id] = true;
            }

            if ($row->jurisdiction_id !== null) {
                $incidents[$id]['jurisdictions'][(int) $row->jurisdiction_id] = [
                    'station_id' => $row->jurisdiction_station_id === null ? null : (int) $row->jurisdiction_station_id,
                    'station' => (string) $row->jurisdiction_station_name,
                ];
            }
        }

        $aggregated = [];

        foreach ($incidents as $incident) {
            [$key, $attributes] = $this->classifyIncidentJurisdiction($incident);

            $aggregated[$key] ??= $attributes + [
                'total' => 0,
                'bairros' => [],
                'incident_ids' => [],
                'area_atuacao_ids' => [],
                'jurisdiction_ids' => [],
                'police_station_ids' => [],
                'match_types' => [],
            ];

            $aggregated[$key]['total']++;
            $aggregated[$key]['bairros'][] = $incident['bairro'];
            $aggregated[$key]['incident_ids'][] = $incident['id'];
            $aggregated[$key]['area_atuacao_ids'] = array_merge(
                $aggregated[$key]['area_atuacao_ids'],
                array_keys($incident['area_atuacao_ids'])
            );
            $aggregated[$key]['jurisdiction_ids'] = array_merge(
                $aggregated[$key]['jurisdiction_ids'],
                array_keys($incident['jurisdictions'])
            );
            $aggregated[$key]['police_station_ids'] = array_merge(
                $aggregated[$key]['police_station_ids'],
                $attributes['police_station_ids']
            );
            $aggregated[$key]['match_types'][] = $attributes['match_type'];
        }

        foreach ($aggregated as &$group) {
            $group['bairros'] = array_values(array_unique($group['bairros']));
            sort($group['bairros'], SORT_STRING);

            foreach (['incident_ids', 'area_atuacao_ids', 'jurisdiction_ids', 'police_station_ids', 'match_types'] as $field) {
                $group[$field] = array_values(array_unique($group[$field]));
                sort($group[$field], SORT_NUMERIC);
            }

            if (in_array($group['match_types'][0] ?? '', ['ambiguo', 'divergencia', 'sem_correspondencia', 'sem_jurisdicao'], true)) {
                $group['verification_status'] = 'pendente';
            }
        }
        unset($group);

        $result = array_values($aggregated);
        usort($result, static fn (array $a, array $b): int => [$b['total'], $a['police_station']] <=> [$a['total'], $b['police_station']]);

        return $result;
    }

    /**
     * @param array{
     *     id: int,
     *     bairro: string,
     *     area_stations: array<int, string>,
     *     area_atuacao_ids: array<int, bool>,
     *     jurisdictions: array<int, array{station_id: int|null, station: string}>
     * } $incident
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function classifyIncidentJurisdiction(array $incident): array
    {
        $areaStationIds = array_keys($incident['area_stations']);
        $jurisdictionStationIds = [];
        $jurisdictionIds = [];

        foreach ($incident['jurisdictions'] as $jurisdictionId => $jurisdiction) {
            $jurisdictionIds[] = $jurisdictionId;

            if ($jurisdiction['station_id'] !== null) {
                $jurisdictionStationIds[$jurisdiction['station_id']] = $jurisdiction['station_id'];
            }
        }

        if ($areaStationIds === []) {
            return ['pendente', [
                'police_station_id' => null,
                'police_station' => 'Pendente de verificação',
                'police_station_ids' => [],
                'match_type' => 'sem_correspondencia',
                'verification_status' => 'pendente',
            ]];
        }

        if ($jurisdictionStationIds === []) {
            return ['sem_jurisdicao', [
                'police_station_id' => null,
                'police_station' => 'Sem jurisdição ativa',
                'police_station_ids' => $areaStationIds,
                'match_type' => 'sem_jurisdicao',
                'verification_status' => 'pendente',
            ]];
        }

        $matchingStationIds = array_values(array_intersect($areaStationIds, $jurisdictionStationIds));

        if (count($areaStationIds) !== 1 || count($matchingStationIds) !== 1) {
            return ['conflito', [
                'police_station_id' => null,
                'police_station' => 'Conflito jurisdicional',
                'police_station_ids' => array_values(array_unique(array_merge($areaStationIds, $jurisdictionStationIds))),
                'match_type' => count($areaStationIds) > 1 ? 'ambiguo' : 'divergencia',
                'verification_status' => 'pendente',
            ]];
        }

        $stationId = $matchingStationIds[0];
        $stationName = $incident['area_stations'][$stationId];
        $matchingJurisdictionIds = [];

        foreach ($incident['jurisdictions'] as $jurisdictionId => $jurisdiction) {
            if ($jurisdiction['station_id'] === $stationId) {
                $matchingJurisdictionIds[] = $jurisdictionId;
                $stationName = $jurisdiction['station'];
            }
        }

        sort($matchingJurisdictionIds, SORT_NUMERIC);

        return ['estacao_'.$stationId, [
            'police_station_id' => $stationId,
            'police_station' => $stationName,
            'jurisdiction_id' => $matchingJurisdictionIds[0],
            'police_station_ids' => [$stationId],
            'match_type' => 'territorial',
            'verification_status' => 'confirmado',
        ]];
    }

    /** @param array<string, mixed> $filters */
    public function byAreaAtuacao(array $filters = []): array
    {
        [$where, $bindings] = $this->baseWhere($filters);

        $rows = DB::select($this->areaCte($where).'
            SELECT l.area_atuacao_id, l.bairro, l.police_station_id,
                   ps.name AS police_station, count(*)::int AS total
            FROM located l
            LEFT JOIN police_stations ps ON ps.id = l.police_station_id
            GROUP BY l.area_atuacao_id, l.bairro, l.police_station_id, ps.name
            ORDER BY total DESC, l.bairro ASC, l.area_atuacao_id ASC
        ', $bindings);

        return array_map(static fn ($row): array => [
            'area_atuacao_id' => (int) $row->area_atuacao_id,
            'bairro' => (string) $row->bairro,
            'police_station_id' => $row->police_station_id === null ? null : (int) $row->police_station_id,
            'police_station' => $row->police_station === null ? null : (string) $row->police_station,
            'total' => (int) $row->total,
        ], $rows);
    }

    /** @param array<string, mixed> $filters */
    private function baseWhere(array $filters): array
    {
        $conditions = [
            'i.is_public = true',
            'i.deleted_at IS NULL',
            'i.latitude IS NOT NULL',
            'i.longitude IS NOT NULL',
        ];
        $bindings = [];

        foreach (['category_id', 'priority', 'status', 'neighborhood'] as $field) {
            if (! empty($filters[$field])) {
                $conditions[] = "i.{$field} = ?";
                $bindings[] = $filters[$field];
            }
        }

        if (! empty($filters['jurisdiction_station_id'])) {
            $conditions[] = PoliceJurisdictionSpatialFilter::existsCondition('i');
            $bindings[] = (int) $filters['jurisdiction_station_id'];
        }

        $bounds = config('map.bounds');

        if (is_array($bounds)) {
            $conditions[] = 'i.latitude BETWEEN ? AND ?';
            $bindings[] = $bounds['south_west']['lat'];
            $bindings[] = $bounds['north_east']['lat'];
            $conditions[] = 'i.longitude BETWEEN ? AND ?';
            $bindings[] = $bounds['south_west']['lng'];
            $bindings[] = $bounds['north_east']['lng'];
        }

        return [implode(' AND ', $conditions), $bindings];
    }

    private function locatedCte(string $where): string
    {
        return '
            WITH located AS (
                SELECT DISTINCT ON (i.id)
                    i.id,
                    i.latitude,
                    i.longitude,
                    CASE WHEN ae.id = 14 AND TRIM(ae."Bairro") = \'Matola A\' THEN 1 ELSE ae.id END AS area_estudo_id,
                    TRIM(ae."Bairro") AS bairro
                FROM incidents i
                JOIN "Area de Estudo" ae
                  ON ae.geom IS NOT NULL
                 AND ST_IsValid(ae.geom)
                 AND ST_SRID(ae.geom) > 0
                 AND ST_Covers(
                     ST_Transform(ae.geom, 4326),
                     ST_SetSRID(ST_MakePoint(i.longitude::double precision, i.latitude::double precision), 4326)
                 )
                WHERE '.$where.'
                ORDER BY i.id,
                    CASE WHEN ae.id = 1 AND TRIM(ae."Bairro") = \'Matola A\' THEN 0 ELSE 1 END,
                    ae.id
            )
        ';
    }

    private function areaCte(string $where): string
    {
        return '
            WITH located AS (
                SELECT DISTINCT ON (i.id)
                    i.id,
                    aa.id AS area_atuacao_id,
                    TRIM(aa."Bairro") AS bairro,
                    aa.police_station_id
                FROM incidents i
                JOIN "Area de Actuacao" aa
                  ON aa.geom IS NOT NULL
                 AND ST_IsValid(aa.geom)
                 AND ST_SRID(aa.geom) > 0
                 AND ST_Covers(
                     ST_Transform(aa.geom, 4326),
                     ST_SetSRID(ST_MakePoint(i.longitude::double precision, i.latitude::double precision), 4326)
                 )
                WHERE '.$where.'
                ORDER BY i.id, aa.id
            )
        ';
    }
}
