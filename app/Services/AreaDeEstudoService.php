<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AreaDeEstudoService
{
    /**
     * Return one canonical record per bairro.
     *
     * The database keeps all original records. Duplicate bairros are collapsed
     * only for presentation/spatial responses; the lowest id is canonical,
     * with Matola A id 1 explicitly preferred over the duplicate id 14.
     */
    public function canonicalAreas(): array
    {
        $rows = array_map(
            fn ($row) => (array) $row,
            DB::select('SELECT * FROM "Area de Estudo" ORDER BY id')
        );

        $groups = [];
        foreach ($rows as $row) {
            $bairro = trim((string) ($row['Bairro'] ?? ''));
            if ($bairro === '') {
                continue;
            }

            if (! isset($groups[$bairro])) {
                $groups[$bairro] = [];
            }
            $groups[$bairro][] = $row;
        }

        $canonical = [];
        foreach ($groups as $bairro => $group) {
            usort($group, fn (array $left, array $right) => $this->canonicalRank($left) <=> $this->canonicalRank($right));

            $winner = $group[0];
            $winner['source_ids'] = array_values(array_map(
                fn (array $row) => (int) $row['id'],
                $group
            ));
            $canonical[] = $winner;
        }

        usort($canonical, fn (array $left, array $right) => ((int) $left['id']) <=> ((int) $right['id']));

        return $canonical;
    }

    public function geometry(int $areaId): ?array
    {
        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(geom) AS geojson FROM "Area de Estudo" WHERE id = ?',
            [$areaId]
        );

        if (! $row || ! $row->geojson) {
            return null;
        }

        return json_decode($row->geojson, true);
    }

    public function distinctBairroCount(): int
    {
        return count($this->canonicalAreas());
    }

    /**
     * Map every original Area de Estudo id to its canonical id.
     *
     * @return array<int, int>
     */
    public function canonicalIdMap(): array
    {
        $map = [];

        foreach ($this->canonicalAreas() as $area) {
            foreach ($area['source_ids'] as $sourceId) {
                $map[$sourceId] = (int) $area['id'];
            }
        }

        return $map;
    }

    /**
     * Unit jurisdiction names grouped by canonical Area de Estudo id.
     *
     * @return array<int, array<int, string>>
     */
    public function unitNamesByCanonicalArea(): array
    {
        $rows = DB::select('
            SELECT uj.area_estudo_id, up.unidade_policial
            FROM unit_jurisdictions uj
            JOIN "unidades policiais" up ON up.id = uj.unidade_policial_id
            WHERE uj.is_active = true
        ');

        return $this->groupNamesByCanonicalArea($rows, 'area_estudo_id', 'unidade_policial');
    }

    /**
     * Police station names grouped by canonical Area de Estudo id.
     *
     * @return array<int, array<int, string>>
     */
    public function stationNamesByCanonicalArea(): array
    {
        $rows = DB::select('
            SELECT j.area_estudo_id, ps.name
            FROM jurisdictions j
            JOIN police_stations ps ON ps.id = j.police_station_id
            WHERE j.is_active = true
        ');

        return $this->groupNamesByCanonicalArea($rows, 'area_estudo_id', 'name');
    }

    private function groupNamesByCanonicalArea(array $rows, string $idKey, string $nameKey): array
    {
        $map = $this->canonicalIdMap();
        $result = [];

        foreach ($rows as $row) {
            $sourceId = (int) $row->{$idKey};
            $canonicalId = $map[$sourceId] ?? null;
            if ($canonicalId === null) {
                continue;
            }

            $name = trim((string) ($row->{$nameKey} ?? ''));
            if ($name === '') {
                continue;
            }

            if (! isset($result[$canonicalId])) {
                $result[$canonicalId] = [];
            }

            if (! in_array($name, $result[$canonicalId], true)) {
                $result[$canonicalId][] = $name;
            }
        }

        return $result;
    }

    private function canonicalRank(array $row): int
    {
        $bairro = trim((string) ($row['Bairro'] ?? ''));
        $id = (int) ($row['id'] ?? 0);

        if ($bairro === 'Matola A' && $id === 1) {
            return 0;
        }

        return 1 + $id;
    }
}
