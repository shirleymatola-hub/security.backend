<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase55cJurisdictionAggregationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requer PostgreSQL/PostGIS.');
        }

        if (! Schema::hasTable('Area de Estudo') || ! Schema::hasTable('Area de Actuacao')) {
            $this->markTestSkipped('Tabelas espaciais indisponíveis.');
        }
    }

    public function test_matola_d_e_atribuida_espacialmente_sem_duplicacao(): void
    {
        $response = $this->getJson('/api/map/incidents?aggregate=all');
        $response->assertOk();

        $groups = $response->json('aggregations.jurisdicao');
        $byStation = collect($groups)->keyBy('police_station_id');
        $conflict = collect($groups)->firstWhere('police_station', 'Conflito jurisdicional');

        $this->assertNotNull($byStation->get(2));
        $this->assertNotNull($byStation->get(10));
        $this->assertContains('Matola D', $byStation->get(2)['bairros']);
        $this->assertContains('Matola D', $byStation->get(10)['bairros']);
        $this->assertNotContains('Matola D', $conflict['bairros']);

        $this->assertSame(
            [30, 115, 116, 117, 118, 119, 121, 123, 124, 125],
            array_values(array_intersect($byStation->get(2)['incident_ids'], [30, 115, 116, 117, 118, 119, 121, 123, 124, 125]))
        );
        $this->assertSame(
            [11, 120, 122],
            array_values(array_intersect($byStation->get(10)['incident_ids'], [11, 120, 122]))
        );

        $this->assertEmpty(array_intersect($byStation->get(2)['incident_ids'], $byStation->get(10)['incident_ids']));
        $this->assertSame([7, 10], $conflict['police_station_ids']);
        $this->assertSame(['Matola J'], $conflict['bairros']);
        $this->assertSame('divergencia', $conflict['match_type']);
        $this->assertSame('pendente', $conflict['verification_status']);

        $ids = collect($groups)->pluck('incident_ids')->flatten()->map(fn ($id) => (int) $id)->all();
        $this->assertCount(count($ids), array_unique($ids));
        $this->assertSame(array_sum(array_column($groups, 'total')), count($ids));

        $expectedInStudyArea = DB::selectOne('
            SELECT count(*)::int AS total
            FROM incidents i
            WHERE i.is_public = true AND i.deleted_at IS NULL
              AND i.latitude IS NOT NULL AND i.longitude IS NOT NULL
              AND i.latitude BETWEEN -26.10 AND -25.70
              AND i.longitude BETWEEN 32.20 AND 32.80
              AND EXISTS (
                  SELECT 1 FROM "Area de Estudo" ae
                  WHERE ae.geom IS NOT NULL AND ST_IsValid(ae.geom) AND ST_SRID(ae.geom) > 0
                    AND ST_Covers(ST_Transform(ae.geom, 4326),
                        ST_SetSRID(ST_MakePoint(i.longitude::double precision, i.latitude::double precision), 4326))
              )')->total;

        $this->assertSame((int) $expectedInStudyArea, count($ids));
    }

    public function test_filtro_espacial_por_jurisdicao_corresponde_aos_agrupamentos(): void
    {
        $groups = $this->getJson('/api/map/incidents?aggregate=jurisdicao')->json('aggregations.jurisdicao');
        $byStation = collect($groups)->keyBy('police_station_id');

        foreach ([2, 10, 7, 11] as $stationId) {
            $response = $this->getJson('/api/map/incidents?jurisdiction_station_id='.$stationId);
            $response->assertOk();

            $ids = collect($response->json('features'))
                ->pluck('properties.id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $expected = $byStation->get($stationId)['incident_ids'];
            sort($expected);

            $this->assertSame($expected, $ids);
            $this->assertCount(count($ids), array_unique($ids));
        }

        $byJurisdictionId = collect($groups)->pluck('jurisdiction_id')->filter()->values()->all();

        foreach ($byJurisdictionId as $jurisdictionId) {
            $byId = $this->getJson('/api/map/incidents?jurisdiction_id='.$jurisdictionId);
            $byId->assertOk();
            $this->assertNotEmpty($byId->json('features'));
        }

        $first = $this->getJson('/api/map/incidents?jurisdiction_id=4')->json('features');
        $second = $this->getJson('/api/map/incidents?jurisdiction_station_id=2')->json('features');
        $this->assertSame(
            collect($second)->pluck('properties.id')->sort()->values()->all(),
            collect($first)->pluck('properties.id')->sort()->values()->all()
        );

        $matolaJ = collect($this->getJson('/api/map/incidents?jurisdiction_station_id=7')->json('features'))
            ->pluck('properties.id');
        $this->assertNotContains(21, $matolaJ->all());
    }

    public function test_contrato_da_api_e_areas_permanece_compativel(): void
    {
        $response = $this->getJson('/api/map/incidents?aggregate=all');
        $groups = $response->json('aggregations.jurisdicao');

        foreach ($groups as $group) {
            $this->assertArrayHasKey('police_station', $group);
            $this->assertArrayHasKey('total', $group);
            $this->assertArrayHasKey('bairros', $group);
            $this->assertArrayHasKey('police_station_ids', $group);
            $this->assertArrayHasKey('incident_ids', $group);
            $this->assertArrayHasKey('match_types', $group);
            $this->assertSame(count($group['incident_ids']), $group['total']);
        }

        $byStation = collect($groups)->keyBy('police_station_id');

        $this->assertContains('Fomento', $byStation->get(11)['bairros']);
        $this->assertContains('Liberdade', $byStation->get(7)['bairros']);
        $this->assertContains('Matola A', $byStation->get(2)['bairros']);
        $this->assertContains('Matola C', $byStation->get(2)['bairros']);
        $this->assertContains('Matola B', $byStation->get(10)['bairros']);
        $this->assertContains('Matola F', $byStation->get(10)['bairros']);
        $this->assertContains('Matola G', $byStation->get(10)['bairros']);
        $this->assertContains('Matola H', $byStation->get(10)['bairros']);
    }

    public function test_poligonos_de_matola_d_sao_distintos_tocantes_e_sem_sobreposicao(): void
    {
        $study = DB::select('SELECT id, ST_SRID(geom) AS srid, ST_IsValid(geom) AS valid
            FROM "Area de Estudo" WHERE TRIM("Bairro") = \'Matola D\' ORDER BY id');
        $areas = DB::select('SELECT aa.id, aa.police_station_id, ST_SRID(aa.geom) AS srid, ST_IsValid(aa.geom) AS valid
            FROM "Area de Actuacao" aa WHERE TRIM(aa."Bairro") = \'Matola D\' ORDER BY aa.id');

        $this->assertCount(1, $study);
        $this->assertSame(7, (int) $study[0]->id);
        $this->assertCount(2, $areas);
        $this->assertSame([1, 10], array_map(fn ($row) => (int) $row->id, $areas));
        $this->assertSame([10, 2], array_map(fn ($row) => (int) $row->police_station_id, $areas));

        foreach (array_merge($study, $areas) as $row) {
            $this->assertSame(4326, (int) $row->srid);
            $this->assertTrue((bool) $row->valid);
        }

        $relation = DB::select('SELECT
                ST_Intersects(a.geom, b.geom) AS intersects,
                ST_Overlaps(a.geom, b.geom) AS overlaps,
                round(ST_Area(ST_Intersection(a.geom, b.geom))::numeric, 10) AS intersection_area,
                round(ST_Area(ST_SymDifference(
                    (SELECT geom FROM "Area de Estudo" WHERE id = 7),
                    ST_Union(a.geom, b.geom)
                ))::numeric, 10) AS symmetric_difference
            FROM "Area de Actuacao" a, "Area de Actuacao" b
            WHERE a.id = 1 AND b.id = 10')[0];

        $this->assertTrue((bool) $relation->intersects);
        $this->assertFalse((bool) $relation->overlaps);
        $this->assertSame(0.0, (float) $relation->intersection_area);
        $this->assertSame(0.0, (float) $relation->symmetric_difference);
    }

    public function test_ocorrencias_de_matola_d_tem_correspondencia_territorial_unica(): void
    {
        $rows = DB::select('
            WITH located AS (
                SELECT DISTINCT ON (i.id) i.id,
                       ST_SetSRID(ST_MakePoint(i.longitude::double precision, i.latitude::double precision), 4326) AS point
                FROM incidents i
                JOIN "Area de Estudo" ae ON ae.geom IS NOT NULL AND ST_IsValid(ae.geom)
                  AND ST_Covers(ST_Transform(ae.geom, 4326),
                      ST_SetSRID(ST_MakePoint(i.longitude::double precision, i.latitude::double precision), 4326))
                WHERE i.is_public = true AND i.deleted_at IS NULL
                  AND i.latitude IS NOT NULL AND i.longitude IS NOT NULL
                  AND TRIM(ae."Bairro") = \'Matola D\'
                ORDER BY i.id, ae.id
            )
            SELECT l.id,
                   count(aa.id)::int AS polygons,
                   bool_or(ST_Intersects(l.point, ST_Boundary(aa.geom))) AS on_boundary
            FROM located l
            JOIN "Area de Actuacao" aa ON aa.geom IS NOT NULL AND ST_IsValid(aa.geom)
              AND ST_Covers(ST_Transform(aa.geom, 4326), l.point)
            GROUP BY l.id
            ORDER BY l.id');

        $this->assertCount(13, $rows);

        foreach ($rows as $row) {
            $this->assertSame(1, (int) $row->polygons);
            $this->assertFalse((bool) $row->on_boundary);
        }
    }
}
