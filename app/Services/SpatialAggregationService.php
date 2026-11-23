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
            bairro_counts AS (
                SELECT area_estudo_id, bairro, count(*)::int AS total
                FROM located
                GROUP BY area_estudo_id, bairro
            )
            SELECT bc.bairro, bc.total,
                   j.id AS jurisdiction_id, j.name AS jurisdiction_name,
                   j.police_station_id, ps.name AS police_station
            FROM bairro_counts bc
            LEFT JOIN jurisdictions j ON j.area_estudo_id = bc.area_estudo_id AND j.is_active = true
            LEFT JOIN police_stations ps ON ps.id = j.police_station_id
            ORDER BY bc.total DESC, bc.bairro ASC, j.id ASC
        ', $bindings);

        $byBairro = [];

        foreach ($rows as $row) {
            $byBairro[$row->bairro][] = $row;
        }

        $aggregated = [];

        foreach ($byBairro as $bairro => $entries) {
            $active = array_values(array_filter($entries, static fn ($row): bool => $row->jurisdiction_id !== null));
            $total = (int) $entries[0]->total;

            if ($active === []) {
                $key = 'sem_jurisdicao';
                $aggregated[$key] ??= [
                    'police_station_id' => null,
                    'police_station' => 'Sem jurisdição ativa',
                    'total' => 0,
                    'bairros' => [],
                ];
                $aggregated[$key]['total'] += $total;
                $aggregated[$key]['bairros'][] = (string) $bairro;

                continue;
            }

            if (count($active) > 1) {
                $key = 'conflito';
                $stationIds = array_values(array_unique(array_map(
                    static fn ($row): int => (int) $row->police_station_id,
                    $active
                )));
                sort($stationIds);
                $aggregated[$key] ??= [
                    'police_station_id' => null,
                    'police_station' => 'Conflito jurisdicional',
                    'total' => 0,
                    'bairros' => [],
                    'police_station_ids' => [],
                ];
                $aggregated[$key]['total'] += $total;
                $aggregated[$key]['bairros'][] = (string) $bairro;
                $aggregated[$key]['police_station_ids'] = array_values(array_unique(array_merge(
                    $aggregated[$key]['police_station_ids'],
                    $stationIds
                )));

                continue;
            }

            $jurisdiction = $active[0];
            $key = 'estacao_'.$jurisdiction->police_station_id;
            $aggregated[$key] ??= [
                'police_station_id' => (int) $jurisdiction->police_station_id,
                'police_station' => (string) $jurisdiction->police_station,
                'total' => 0,
                'bairros' => [],
                'jurisdiction_id' => (int) $jurisdiction->jurisdiction_id,
            ];
            $aggregated[$key]['total'] += $total;
            $aggregated[$key]['bairros'][] = (string) $bairro;
        }

        $result = array_values($aggregated);
        usort($result, static fn (array $a, array $b): int => [$b['total'], $a['police_station'] ?? ''] <=> [$a['total'], $b['police_station'] ?? '']);

        return $result;
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
