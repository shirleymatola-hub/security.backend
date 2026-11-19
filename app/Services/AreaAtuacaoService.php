<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AreaAtuacaoService
{
    /**
     * Build the Area de Actuación GeoJSON from the original PostGIS table.
     *
     * No geometry or police-station assignment is changed here. The table's
     * police_station_id remains authoritative; spatial unit matches and
     * jurisdiction conflicts are exposed as metadata only.
     *
     * @param  array<int, int>|null  $stationIds
     * @param  array<int, string>|null  $bairros
     */
    public function geoJson(?array $stationIds = null, ?array $bairros = null, ?int $areaId = null): array
    {
        $sql = '
            SELECT
                aa.id,
                aa."Bairro" AS bairro,
                aa."CodBairro" AS cod_bairro,
                aa."CodLocal" AS cod_local,
                aa."Localidade" AS localidade,
                aa."CodPost" AS cod_posto,
                aa."Posto" AS posto,
                aa."CodDist" AS cod_distrito,
                aa."Distrito" AS distrito,
                aa."CodProv" AS cod_provincia,
                aa."Provincia" AS provincia,
                aa.layer,
                aa.path,
                aa."Shape_Area" AS shape_area,
                ST_Area(aa.geom::geography) / 1000000.0 AS area_km2,
                aa.police_station_id,
                ps.name AS police_station_name,
                ST_AsGeoJSON('.AreaAtuacaoSpatialFilter::transformedGeometry().') AS geojson
            FROM "Area de Actuacao" aa
            LEFT JOIN police_stations ps ON ps.id = aa.police_station_id
            WHERE aa.geom IS NOT NULL AND ST_IsValid(aa.geom)
        ';

        $bindings = [];

        if ($stationIds !== null) {
            $ids = array_values(array_filter(array_map('intval', $stationIds)));
            if ($ids === []) {
                return ['type' => 'FeatureCollection', 'features' => []];
            }
            $sql .= ' AND aa.police_station_id IN ('.implode(',', array_fill(0, count($ids), '?')).')';
            $bindings = array_merge($bindings, $ids);
        }

        if ($bairros !== null) {
            $names = array_values(array_filter($bairros, fn ($name) => is_string($name) && trim($name) !== ''));
            if ($names === []) {
                return ['type' => 'FeatureCollection', 'features' => []];
            }
            $sql .= ' AND aa."Bairro" IN ('.implode(',', array_fill(0, count($names), '?')).')';
            $bindings = array_merge($bindings, $names);
        }

        if ($areaId !== null) {
            $sql .= ' AND aa.id = ?';
            $bindings[] = $areaId;
        }

        $sql .= ' ORDER BY aa.id';

        $rows = DB::select($sql, $bindings);
        $unitsByArea = $this->unitsInsideAreas();
        $jurisdictionsByBairro = $this->jurisdictionStationsByBairro();
        $administrativeByBairro = $this->tutelaAdministrativaByBairro();
        $splitBairros = $this->splitBairros();

        $features = [];

        foreach ($rows as $row) {
            $geometry = json_decode($row->geojson ?? '', true);
            if (! is_array($geometry)) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'id' => (int) $row->id,
                'geometry' => $geometry,
                'properties' => $this->properties($row, $unitsByArea, $jurisdictionsByBairro, $administrativeByBairro, $splitBairros),
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    public function findAtPoint(float $latitude, float $longitude): array
    {
        $rows = array_map(
            fn ($row) => (array) $row,
            DB::select('
                SELECT
                    aa.id,
                    aa."Bairro" AS bairro,
                    aa."CodBairro" AS cod_bairro,
                    aa.police_station_id,
                    ps.name AS police_station_name,
                    aa.layer,
                    aa.path
                FROM "Area de Actuacao" aa
                LEFT JOIN police_stations ps ON ps.id = aa.police_station_id
                WHERE aa.geom IS NOT NULL AND ST_IsValid(aa.geom)
                  AND ST_Covers('
                    .AreaAtuacaoSpatialFilter::transformedGeometry()
                    .', ST_SetSRID(ST_MakePoint(?::double precision, ?::double precision), 4326))
                ORDER BY aa.id
            ', [$longitude, $latitude])
        );

        $administrativeByBairro = $this->tutelaAdministrativaByBairro();

        $areas = array_map(function (array $row) use ($administrativeByBairro): array {
            $administrative = $this->lookupAdministrative($administrativeByBairro, (string) $row['bairro']);

            return [
                'id' => (int) $row['id'],
                'bairro' => $row['bairro'],
                'cod_bairro' => $row['cod_bairro'],
                'police_station_id' => $row['police_station_id'] === null ? null : (int) $row['police_station_id'],
                'police_station_name' => $row['police_station_name'],
                'layer' => $row['layer'],
                'path' => $row['path'],
                'responsabilidade_territorial' => $this->territorialResponsibility($row),
                'tutela_administrativa' => $administrative,
                'avisos' => $this->buildAvisos(
                    $row['police_station_id'] === null ? null : (int) $row['police_station_id'],
                    $administrative
                ),
            ];
        }, $rows);

        $single = count($areas) === 1 ? $areas[0] : null;

        return [
            'status' => count($areas) === 0 ? 'not_found' : (count($areas) > 1 ? 'ambiguous' : 'matched'),
            'ambiguous' => count($areas) > 1,
            'coordinates' => ['lat' => $latitude, 'lng' => $longitude],
            'areas' => $areas,
            'responsabilidade_territorial' => $single
                ? $single['responsabilidade_territorial']
                : [
                    'estado' => count($areas) === 0 ? 'sem_area_operacional' : 'ambiguo',
                    'areas' => array_map(fn (array $area) => $area['responsabilidade_territorial'], $areas),
                ],
            'tutela_administrativa' => $single
                ? $single['tutela_administrativa']
                : [
                    'estado' => $single === null && $areas === [] ? 'sem_area_operacional' : 'multiplas_areas',
                    'fontes' => [],
                    'estacoes' => [],
                ],
            'avisos' => $single ? $single['avisos'] : [],
        ];
    }

    public function find(int $areaId): ?array
    {
        $feature = $this->geoJson(null, null, $areaId)['features'][0] ?? null;

        return $feature ?: null;
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function unitsInsideAreas(): array
    {
        $rows = DB::select('
            SELECT
                aa.id AS area_id,
                up.id,
                up.unidade_policial,
                up.esquadra_tutela,
                up.police_station_id,
                up.latitude,
                up.longitude,
                ps.name AS police_station_name
            FROM "Area de Actuacao" aa
            JOIN "unidades policiais" up
              ON up.latitude IS NOT NULL
             AND up.longitude IS NOT NULL
             AND up.latitude BETWEEN -90 AND 90
             AND up.longitude BETWEEN -180 AND 180
             AND ST_Covers('
                .AreaAtuacaoSpatialFilter::transformedGeometry()
                .', ST_SetSRID(ST_MakePoint(up.longitude::double precision, up.latitude::double precision), 4326))
            LEFT JOIN police_stations ps ON ps.id = up.police_station_id
            WHERE aa.geom IS NOT NULL AND ST_IsValid(aa.geom)
            ORDER BY aa.id, up.id
        ');

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row->area_id][] = [
                'id' => (int) $row->id,
                'unidade_policial' => $row->unidade_policial,
                'esquadra_tutela' => $row->esquadra_tutela,
                'police_station_id' => (int) $row->police_station_id,
                'police_station_name' => $row->police_station_name,
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
            ];
        }

        return $result;
    }

    public function homeBairro(float $latitude, float $longitude): ?string
    {
        $row = DB::selectOne('
            SELECT "Bairro" AS bairro
            FROM "Area de Estudo"
            WHERE geom IS NOT NULL AND ST_IsValid(geom)
              AND ST_Covers(geom, ST_SetSRID(ST_MakePoint(?::double precision, ?::double precision), 4326))
            ORDER BY id
            LIMIT 1
        ', [$longitude, $latitude]);

        return $row?->bairro;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function jurisdictionStationsByBairro(): array
    {
        $rows = DB::select('
            SELECT
                ae."Bairro" AS bairro,
                ps.id AS station_id,
                ps.name AS station_name
            FROM jurisdictions j
            JOIN "Area de Estudo" ae ON ae.id = j.area_estudo_id
            JOIN police_stations ps ON ps.id = j.police_station_id
            WHERE j.is_active = true AND ae."Bairro" IS NOT NULL
            GROUP BY ae."Bairro", ps.id, ps.name
            ORDER BY ae."Bairro", ps.id
        ');

        $result = [];

        foreach ($rows as $row) {
            $result[$row->bairro][] = [
                'id' => (int) $row->station_id,
                'name' => $row->station_name,
            ];
        }

        return $result;
    }

    public function administrativeSourcesByStation(int $stationId): array
    {
        $jurisdictions = DB::select('
            SELECT
                j.id AS source_id,
                j.police_station_id,
                ps.name AS police_station_name
            FROM jurisdictions j
            JOIN police_stations ps ON ps.id = j.police_station_id
            WHERE j.police_station_id = ?
              AND j.is_active = true
            ORDER BY j.id
        ', [$stationId]);

        $neighborhoodLinks = DB::select('
            SELECT
                n.id AS source_id,
                ps.id AS police_station_id,
                ps.name AS police_station_name
            FROM neighborhood_police_station nps
            JOIN neighborhoods n ON n.id = nps.neighborhood_id
            JOIN police_stations ps ON ps.id = nps.police_station_id
            WHERE nps.police_station_id = ?
            ORDER BY n.id, ps.id
        ', [$stationId]);

        return $this->administrativePayload([
            'jurisdictions' => array_map(static fn ($row) => (array) $row, $jurisdictions),
            'neighborhood_police_station' => array_map(static fn ($row) => (array) $row, $neighborhoodLinks),
        ]);
    }

    private function tutelaAdministrativaByBairro(): array
    {
        $jurisdictions = DB::select('
            SELECT
                j.id AS source_id,
                j.police_station_id,
                ps.name AS police_station_name,
                ae."Bairro" AS bairro
            FROM jurisdictions j
            JOIN "Area de Estudo" ae ON ae.id = j.area_estudo_id
            JOIN police_stations ps ON ps.id = j.police_station_id
            WHERE j.is_active = true AND ae."Bairro" IS NOT NULL
            ORDER BY ae."Bairro", j.id
        ');

        $neighborhoodLinks = DB::select('
            SELECT
                n.id AS source_id,
                n.name AS bairro,
                ps.id AS police_station_id,
                ps.name AS police_station_name
            FROM neighborhood_police_station nps
            JOIN neighborhoods n ON n.id = nps.neighborhood_id
            JOIN police_stations ps ON ps.id = nps.police_station_id
            ORDER BY n.name, ps.id
        ');

        $result = [];

        foreach (array_map(static fn ($row) => (array) $row, $jurisdictions) as $row) {
            $key = $this->bairroKey((string) $row['bairro']);
            $result[$key]['jurisdictions'][] = $row;
        }

        foreach (array_map(static fn ($row) => (array) $row, $neighborhoodLinks) as $row) {
            $key = $this->bairroKey((string) $row['bairro']);
            $result[$key]['neighborhood_police_station'][] = $row;
        }

        foreach ($result as $key => $sources) {
            $result[$key] = $this->administrativePayload($sources);
        }

        return $result;
    }

    private function administrativePayload(array $sources): array
    {
        $fontes = [];
        $estacoes = [];

        foreach ($sources as $fonte => $rows) {
            if ($rows === []) {
                continue;
            }

            $registos = [];
            foreach ($rows as $row) {
                $stationId = isset($row['police_station_id']) && $row['police_station_id'] !== null
                    ? (int) $row['police_station_id']
                    : null;

                if ($stationId !== null && ! isset($estacoes[$stationId])) {
                    $estacoes[$stationId] = [
                        'police_station_id' => $stationId,
                        'police_station_name' => $row['police_station_name'] ?? null,
                    ];
                }

                $registos[] = [
                    'source_id' => isset($row['source_id']) ? (int) $row['source_id'] : null,
                    'police_station_id' => $stationId,
                    'police_station_name' => $row['police_station_name'] ?? null,
                ];
            }

            $fontes[] = [
                'fonte' => $fonte,
                'registos' => $registos,
            ];
        }

        $estacoes = array_values($estacoes);
        usort($estacoes, fn (array $left, array $right) => $left['police_station_id'] <=> $right['police_station_id']);

        return [
            'estado' => $estacoes === [] ? 'sem_registo_administrativo' : 'registos_disponiveis',
            'fontes' => $fontes,
            'estacoes' => $estacoes,
        ];
    }

    private function lookupAdministrative(array $map, string $bairro): array
    {
        return $map[$this->bairroKey($bairro)] ?? [
            'estado' => 'sem_registo_administrativo',
            'fontes' => [],
            'estacoes' => [],
        ];
    }

    private function bairroKey(string $bairro): string
    {
        $bairro = trim($bairro);

        return function_exists('mb_strtolower') ? mb_strtolower($bairro, 'UTF-8') : strtolower($bairro);
    }

    private function territorialResponsibility($row): array
    {
        $row = (array) $row;
        $stationId = $row['police_station_id'] ?? null;
        $stationId = $stationId === null ? null : (int) $stationId;
        $areaId = (int) ($row['id'] ?? 0);

        return [
            'estado' => $stationId === null ? 'sem_esquadra_territorial' : 'definida',
            'police_station_id' => $stationId,
            'police_station_name' => $stationId === null
                ? null
                : $this->cleanDisplayName((string) ($row['police_station_name'] ?? '')),
            'area_de_atuacao_id' => $areaId,
            'bairro' => $row['bairro'] ?? null,
            'fonte' => [
                'tabela' => 'Area de Actuacao',
                'campo_geometria' => 'geom',
                'source_id' => $areaId,
                'campo_esquadra' => 'police_station_id',
            ],
        ];
    }

    private function buildAvisos(?int $territorialStationId, array $administrative): array
    {
        $avisos = [];
        $stationIds = array_values(array_filter(array_column($administrative['estacoes'] ?? [], 'police_station_id')));
        $distinctStationIds = array_values(array_unique($stationIds));

        if ($territorialStationId !== null && $distinctStationIds !== [] && ! in_array($territorialStationId, $distinctStationIds, true)) {
            $avisos[] = [
                'tipo' => 'divergencia_territorial',
                'mensagem' => 'A esquadra indicada pelo polígono difere das esquadras registadas administrativamente.',
                'fonte' => 'Area de Actuacao',
            ];
        }

        if (count($distinctStationIds) > 1) {
            $avisos[] = [
                'tipo' => 'divergencia_administrativa',
                'mensagem' => 'Existem registos administrativos divergentes para este bairro.',
                'fontes' => array_column($administrative['fontes'] ?? [], 'fonte'),
            ];
        }

        if (count($distinctStationIds) > 1 || $distinctStationIds === []) {
            $avisos[] = [
                'tipo' => 'informacao_pendente_confirmacao',
                'mensagem' => 'A tutela administrativa depende de validação documental.',
            ];
        }

        return $avisos;
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function splitBairros(): array
    {
        $rows = DB::select('SELECT id, "Bairro" AS bairro FROM "Area de Actuacao" ORDER BY id');
        $result = [];

        foreach ($rows as $row) {
            if ($row->bairro === null || trim((string) $row->bairro) === '') {
                continue;
            }
            $result[$row->bairro][] = (int) $row->id;
        }

        return array_filter($result, fn (array $ids) => count($ids) > 1);
    }

    private function properties(
        object $row,
        array $unitsByArea,
        array $jurisdictionsByBairro,
        array $administrativeByBairro,
        array $splitBairros
    ): array {
        $areaId = (int) $row->id;
        $bairro = (string) $row->bairro;
        $stationId = $row->police_station_id === null ? null : (int) $row->police_station_id;
        $units = $unitsByArea[$areaId] ?? [];
        $jurisdictions = $jurisdictionsByBairro[$bairro] ?? [];
        $administrative = $this->lookupAdministrative($administrativeByBairro, $bairro);
        $warnings = [];

        if (isset($splitBairros[$bairro])) {
            $warnings[] = sprintf(
                'Bairro dividido em %d áreas de actuação; as geometrias originais foram preservadas.',
                count($splitBairros[$bairro])
            );
        }

        if ($stationId !== null && $jurisdictions !== []) {
            $jurisdictionIds = array_column($jurisdictions, 'id');
            if (! in_array($stationId, $jurisdictionIds, true)) {
                $warnings[] = 'Divergência entre a Área de Actuação e a tabela jurisdictions: '
                    .implode(' / ', array_map(
                        fn (array $station) => $this->cleanDisplayName((string) $station['name']),
                        $jurisdictions
                    ));
            }
        }

        foreach ($units as $unit) {
            if ($stationId !== null && (int) $unit['police_station_id'] !== $stationId) {
                $warnings[] = sprintf(
                    'A unidade %s está fisicamente dentro desta área, mas pertence a outra esquadra.',
                    $unit['unidade_policial']
                );
            }
        }

        return [
            'id' => $areaId,
            'area_de_atuacao_id' => $areaId,
            'bairro' => $bairro,
            'cod_bairro' => $row->cod_bairro,
            'cod_local' => $row->cod_local,
            'localidade' => $row->localidade,
            'cod_posto' => $row->cod_posto,
            'posto' => $row->posto,
            'cod_distrito' => $row->cod_distrito,
            'distrito' => $row->distrito,
            'cod_provincia' => $row->cod_provincia,
            'provincia' => $row->provincia,
            'police_station_id' => $stationId,
            'police_station_name' => $row->police_station_name,
            'esquadra_responsavel' => $this->cleanDisplayName((string) ($row->police_station_name ?? '')),
            'layer' => $row->layer,
            'path' => $row->path,
            'shape_area' => $row->shape_area === null ? null : (float) $row->shape_area,
            'area_km2' => $row->area_km2 === null ? null : round((float) $row->area_km2, 2),
            'source_table' => 'Area de Actuacao',
            'split_area_ids' => $splitBairros[$bairro] ?? [],
            'is_split_bairro' => isset($splitBairros[$bairro]),
            'units' => $units,
            'jurisdiction_stations' => $jurisdictions,
            'responsabilidade_territorial' => $this->territorialResponsibility($row),
            'tutela_administrativa' => $administrative,
            'avisos' => $this->buildAvisos($stationId, $administrative),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function cleanDisplayName(string $name): string
    {
        $parts = explode(' - ', $name, 2);

        return trim($parts[0]);
    }
}
