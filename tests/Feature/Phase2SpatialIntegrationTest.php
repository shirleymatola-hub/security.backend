<?php

namespace Tests\Feature;

use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Services\IncidentService;
use App\Services\SuperAdmin\PoliceInfrastructureService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase2SpatialIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requer PostgreSQL/PostGIS com smp_test.');
        }

        if (! Schema::hasTable('Area de Estudo') || ! Schema::hasTable('Area de Actuacao')) {
            $this->markTestSkipped('Tabelas espaciais indisponíveis.');
        }
    }

    public function test_area_de_estudo_apresenta_bairros_sem_duplicar_matola_a(): void
    {
        $response = $this->getJson('/api/area-de-estudo');

        $response->assertOk();
        $features = $response->json('features');

        $this->assertCount(13, $features);
        $this->assertSame(13, $response->json('metadata.distinct_bairros'));

        $matolaA = collect($features)->where('properties.bairro', 'Matola A');

        $this->assertCount(1, $matolaA);
        $this->assertSame(1, $matolaA->first()['properties']['id']);
        $this->assertSame([1, 14], $matolaA->first()['properties']['source_ids']);
    }

    public function test_filtro_da_area_de_estudo_devolve_apenas_ocorrencias_publicas_dentro_dos_limites(): void
    {
        $response = $this->getJson('/api/map/incidents?area_de_estudo=1');

        $response->assertOk();
        $returnedIds = collect($response->json('features'))
            ->pluck('properties.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $expectedIds = array_map(
            fn ($row) => (int) $row->id,
            DB::select('
                SELECT i.id
                FROM incidents i
                WHERE i.is_public = true
                  AND i.latitude IS NOT NULL
                  AND i.longitude IS NOT NULL
                  AND EXISTS (
                      SELECT 1
                      FROM "Area de Estudo" ae
                      WHERE ae.geom IS NOT NULL
                        AND ST_IsValid(ae.geom)
                        AND ST_Covers(
                            ST_Transform(ae.geom, 4326),
                            ST_SetSRID(ST_MakePoint(i.longitude::double precision, i.latitude::double precision), 4326)
                        )
                  )
                ORDER BY i.id
            ')
        );

        $this->assertSame($expectedIds, $returnedIds);
        $this->assertNotEmpty($returnedIds);
    }

    public function test_filtro_da_area_de_estudo_por_id_inclui_registos_duplicados_do_bairro(): void
    {
        $response = $this->getJson('/api/map/incidents?area_de_estudo_id=1');

        $response->assertOk();
        $returnedIds = collect($response->json('features'))
            ->pluck('properties.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $expectedIds = array_map(
            fn ($row) => (int) $row->id,
            DB::select('
                SELECT i.id
                FROM incidents i
                WHERE i.is_public = true
                  AND i.latitude IS NOT NULL
                  AND i.longitude IS NOT NULL
                  AND EXISTS (
                      SELECT 1
                      FROM "Area de Estudo" ae
                      WHERE ae.geom IS NOT NULL
                        AND ST_IsValid(ae.geom)
                        AND TRIM(ae."Bairro") = (
                            SELECT TRIM(aex."Bairro")
                            FROM "Area de Estudo" aex
                            WHERE aex.id = ?
                        )
                        AND ST_Covers(
                            ST_Transform(ae.geom, 4326),
                            ST_SetSRID(ST_MakePoint(i.longitude::double precision, i.latitude::double precision), 4326)
                        )
                  )
                ORDER BY i.id
            ', [1])
        );

        $this->assertSame($expectedIds, $returnedIds);
        $this->assertNotEmpty($returnedIds);
    }

    public function test_filtro_da_area_de_actuacao_por_query_param_devolve_ocorrencias_publicas(): void
    {
        $areaId = 10;

        $response = $this->getJson("/api/map/incidents?area_de_atuacao={$areaId}");

        $response->assertOk();
        $returnedIds = collect($response->json('features'))
            ->pluck('properties.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $expectedIds = array_map(
            fn ($row) => (int) $row->id,
            DB::select('
                SELECT i.id
                FROM incidents i
                WHERE i.is_public = true
                  AND i.latitude IS NOT NULL
                  AND i.longitude IS NOT NULL
                  AND EXISTS (
                      SELECT 1
                      FROM "Area de Actuacao" aa
                      WHERE aa.id = ?
                        AND aa.geom IS NOT NULL
                        AND ST_IsValid(aa.geom)
                        AND ST_Covers(
                            aa.geom,
                            ST_SetSRID(ST_MakePoint(i.longitude::double precision, i.latitude::double precision), 4326)
                        )
                  )
                ORDER BY i.id
            ', [$areaId])
        );

        $this->assertSame($expectedIds, $returnedIds);
        $this->assertNotEmpty($returnedIds);
    }

    public function test_area_de_actuacao_mantem_14_poligonos_e_divisao_de_matola_d(): void
    {
        $response = $this->getJson('/api/area-de-atuacao/map');

        $response->assertOk();
        $features = $response->json('features');

        $this->assertCount(14, $features);
        $this->assertSame(14, $response->json('metadata.total_areas'));

        $matolaD = collect($features)
            ->where('properties.bairro', 'Matola D')
            ->sortBy('properties.id')
            ->values();

        $this->assertCount(2, $matolaD);
        $this->assertSame([1, 10], $matolaD->pluck('properties.id')->all());
        $this->assertSame([10, 2], $matolaD->pluck('properties.police_station_id')->all());
        $this->assertTrue($matolaD->every(fn ($feature) => $feature['properties']['is_split_bairro']));
    }

    public function test_area_de_actuacao_documenta_conflito_de_matola_j(): void
    {
        $features = $this->getJson('/api/area-de-atuacao/map')->json('features');

        $matolaJ = collect($features)->firstWhere('properties.bairro', 'Matola J');

        $this->assertNotNull($matolaJ);
        $this->assertSame(10, $matolaJ['properties']['police_station_id']);
        $this->assertContains(7, array_column($matolaJ['properties']['jurisdiction_stations'], 'id'));
        $this->assertNotEmpty($matolaJ['properties']['warnings']);
    }

    public function test_identificacao_por_coordenada_nao_atribui_area_arbitraria(): void
    {
        $point = DB::selectOne('
            SELECT i.latitude, i.longitude
            FROM incidents i
            WHERE i.is_public = true
              AND i.latitude IS NOT NULL
              AND i.longitude IS NOT NULL
              AND (
                  SELECT COUNT(*)
                  FROM "Area de Actuacao" aa
                  WHERE aa.geom IS NOT NULL
                    AND ST_IsValid(aa.geom)
                    AND ST_Covers(
                        aa.geom,
                        ST_SetSRID(ST_MakePoint(i.longitude::double precision, i.latitude::double precision), 4326)
                    )
              ) = 1
            LIMIT 1
        ');

        $this->assertNotNull($point);

        $response = $this->getJson('/api/area-de-atuacao/locate?lat='.$point->latitude.'&lng='.$point->longitude);

        $response->assertOk();
        $this->assertSame('matched', $response->json('status'));
        $this->assertFalse($response->json('ambiguous'));
        $this->assertCount(1, $response->json('areas'));
    }

    public function test_ocorrencias_da_area_de_actuacao_sao_publicas_e_dentro_da_geometria(): void
    {
        $areaId = 10;

        $response = $this->getJson("/api/area-de-atuacao/{$areaId}/incidents");

        $response->assertOk();
        $returnedIds = collect($response->json('features'))
            ->pluck('properties.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $expectedIds = array_map(
            fn ($row) => (int) $row->id,
            DB::select('
                SELECT i.id
                FROM incidents i
                WHERE i.is_public = true
                  AND i.latitude IS NOT NULL
                  AND i.longitude IS NOT NULL
                  AND EXISTS (
                      SELECT 1
                      FROM "Area de Actuacao" aa
                      WHERE aa.id = ?
                        AND aa.geom IS NOT NULL
                        AND ST_IsValid(aa.geom)
                        AND ST_Covers(
                            aa.geom,
                            ST_SetSRID(ST_MakePoint(i.longitude::double precision, i.latitude::double precision), 4326)
                        )
                  )
                ORDER BY i.id
            ', [$areaId])
        );

        $this->assertSame($expectedIds, $returnedIds);

        $privateIds = DB::table('incidents')
            ->where('is_public', false)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertEmpty(array_intersect($privateIds, $returnedIds));
    }

    public function test_mapa_central_filtra_ocorrencias_e_heatmap_pela_area_de_estudo(): void
    {
        $incidents = app(IncidentService::class);
        $all = $incidents->getMapData();
        $scoped = $incidents->getMapData(withinStudyArea: true);
        $heatmap = $incidents->getHeatmapData(withinStudyArea: true);

        $this->assertNotEmpty($all);
        $this->assertNotEmpty($scoped);
        $this->assertLessThan(count($all), count($scoped));
        $this->assertCount(count($scoped), $heatmap);
        $this->assertEmpty(array_diff(
            array_column($scoped, 'id'),
            array_column($all, 'id'),
        ));
    }

    public function test_infraestruturas_policiais_sao_classificadas_sem_duplicados(): void
    {
        $collection = app(PoliceInfrastructureService::class)->features(true);
        $features = $collection['features'];

        $this->assertNotEmpty($features);
        $this->assertSame(count($features), $collection['metadata']['total']);

        $types = array_unique(array_column(array_column($features, 'properties'), 'tipo_unidade'));
        $this->assertContains('esquadra', $types);
        $this->assertContains('posto', $types);
        $this->assertContains('sernic', $types);

        $keys = array_map(function (array $feature) {
            $props = $feature['properties'];
            return $props['tipo_unidade'].'|'.$props['latitude'].'|'.$props['longitude'];
        }, $features);
        $this->assertCount(count($keys), array_unique($keys));
    }

    public function test_dashboard_e_rota_de_infraestruturas_usam_a_area_de_estudo(): void
    {
        $this->assertTrue(Route::has('super-admin.map.infraestruturas'));

        $infrastructure = app(PoliceInfrastructureService::class)->features(true);
        $response = app(DashboardController::class)->index();
        $stats = $response->getData(true)['stats'];

        $this->assertSame(13, $stats['neighborhoods']);
        $this->assertSame($infrastructure['metadata']['total'], $stats['police_units']);
        $this->assertSame($infrastructure['metadata']['esquadras'], $stats['police_units_by_type']['esquadras']);
        $this->assertSame($infrastructure['metadata']['postos'], $stats['police_units_by_type']['postos']);
        $this->assertSame($infrastructure['metadata']['sernic'], $stats['police_units_by_type']['sernic']);
    }
}
