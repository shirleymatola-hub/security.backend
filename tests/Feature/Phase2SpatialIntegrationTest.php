<?php

namespace Tests\Feature;

use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Models\User;
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
        $this->assertTrue(collect($features)->every(fn ($feature) => is_numeric($feature['properties']['area_km2']) && $feature['properties']['area_km2'] > 0));
    }

    public function test_area_de_actuacao_expõe_estações_para_simbolização_por_id(): void
    {
        $features = $this->getJson('/api/area-de-atuacao/map')->json('features');
        $expected = [
            2 => '1a Esquadra PRM Matola',
            7 => '4a Esquadra da Liberdade',
            10 => '2ª Esquadra PRM Matola',
            11 => '3a Esquadra PRM Matola',
        ];

        $this->assertCount(14, $features);

        foreach ($features as $feature) {
            $stationId = $feature['properties']['police_station_id'];
            $this->assertArrayHasKey($stationId, $expected);
            $this->assertSame($expected[$stationId], trim($feature['properties']['police_station_name']));
        }
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

        foreach ($features as $feature) {
            $this->assertIsArray($feature['properties']['source_ids']);
            $this->assertContains($feature['id'], $feature['properties']['source_ids']);
            $this->assertNotEmpty($feature['properties']['source_records']);
        }
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

    public function test_jurisdictions_usam_geometria_da_area_de_estudo_sem_expor_metadados_sensiveis(): void
    {
        $response = $this->getJson('/api/jurisdictions');

        $response->assertOk();
        $features = $response->json('features');

        $this->assertNotEmpty($features);

        foreach ($features as $feature) {
            $this->assertSame('Feature', $feature['type']);
            $this->assertIsArray($feature['geometry']);
            $this->assertArrayHasKey('coordinates', $feature['geometry']);
            $this->assertArrayNotHasKey('source_document', $feature['properties']);
            $this->assertArrayNotHasKey('effective_date', $feature['properties']);
            $this->assertArrayNotHasKey('description', $feature['properties']);
        }
    }

    public function test_summary_de_jurisdictions_nao_expõe_documento_de_origem(): void
    {
        $response = $this->getJson('/api/jurisdictions/summary');

        $response->assertOk();

        foreach ($response->json() as $station) {
            foreach ($station['bairros'] as $bairro) {
                $this->assertArrayNotHasKey('source_document', $bairro);
                $this->assertArrayNotHasKey('effective_date', $bairro);
            }
        }
    }

    public function test_area_de_actuacao_separa_geometria_de_metadados_administrativos(): void
    {
        $features = $this->getJson('/api/area-de-atuacao/map')->json('features');

        $this->assertNotEmpty($features);

        foreach ($features as $feature) {
            $this->assertSame('Area de Actuacao', $feature['properties']['source_table']);

            foreach ($feature['properties']['jurisdiction_stations'] as $station) {
                $this->assertArrayHasKey('id', $station);
                $this->assertArrayHasKey('name', $station);
                $this->assertArrayNotHasKey('geometry', $station);
            }
        }
    }

    public function test_matola_j_expõe_responsabilidade_territorial_e_tutela_administrativa_distintas(): void
    {
        $features = $this->getJson('/api/area-de-atuacao/map')->json('features');
        $matolaJ = collect($features)->firstWhere('properties.bairro', 'Matola J');

        $this->assertNotNull($matolaJ);

        $properties = $matolaJ['properties'];
        $this->assertSame(10, $properties['police_station_id']);
        $this->assertSame(10, $properties['responsabilidade_territorial']['police_station_id']);
        $this->assertSame(6, $properties['responsabilidade_territorial']['fonte']['source_id']);
        $this->assertSame('Area de Actuacao', $properties['responsabilidade_territorial']['fonte']['tabela']);

        $fontes = array_column($properties['tutela_administrativa']['fontes'], 'fonte');
        $this->assertContains('jurisdictions', $fontes);
        $this->assertContains('neighborhood_police_station', $fontes);

        $stationIds = array_column($properties['tutela_administrativa']['estacoes'], 'police_station_id');
        sort($stationIds);
        $this->assertSame([2, 7], $stationIds);

        $tiposAviso = array_column($properties['avisos'], 'tipo');
        $this->assertContains('divergencia_territorial', $tiposAviso);
        $this->assertContains('divergencia_administrativa', $tiposAviso);
        $this->assertContains('informacao_pendente_confirmacao', $tiposAviso);
        $this->assertNotEmpty($properties['warnings']);
        $this->assertStringNotContainsString('source_document', json_encode($properties));
        $this->assertStringNotContainsString('Informação de trabalho', json_encode($properties));
    }

    public function test_matola_d_usa_poligono_que_cobre_coordenada_para_identificar_responsabilidade(): void
    {
        foreach ([1 => 10, 10 => 2] as $areaId => $expectedStationId) {
            $point = DB::selectOne('
                SELECT
                    ST_Y(ST_PointOnSurface(geom4326)) AS latitude,
                    ST_X(ST_PointOnSurface(geom4326)) AS longitude
                FROM (
                    SELECT CASE WHEN ST_SRID(aa.geom) = 4326 THEN aa.geom ELSE ST_Transform(aa.geom, 4326) END AS geom4326
                    FROM "Area de Actuacao" aa
                    WHERE aa.id = ? AND aa.geom IS NOT NULL AND ST_IsValid(aa.geom)
                ) AS normalized
            ', [$areaId]);

            $this->assertNotNull($point);

            $response = $this->getJson('/api/area-de-atuacao/locate?lat='.$point->latitude.'&lng='.$point->longitude);
            $response->assertOk();

            $area = collect($response->json('areas'))->firstWhere('id', $areaId);
            $this->assertNotNull($area);
            $this->assertSame($expectedStationId, $area['responsabilidade_territorial']['police_station_id']);
            $this->assertSame($areaId, $area['responsabilidade_territorial']['fonte']['source_id']);
        }
    }

    public function test_area_do_utilizador_da_estacao_1_indica_ausencia_de_area_operacional(): void
    {
        $user = User::find(1);

        $this->assertNotNull($user);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/area-de-atuacao');
        $response->assertOk();

        $this->assertFalse($response->json('area_available'));
        $this->assertSame('sem_area_operacional', $response->json('area_status'));
        $this->assertSame('Não existe uma Área de Actuação operacional associada a esta unidade policial.', $response->json('message'));
        $this->assertEmpty($response->json('features'));
        $this->assertSame('sem_area_operacional', $response->json('responsabilidade_territorial.estado'));
        $this->assertContains('sem_area_operacional', array_column($response->json('avisos'), 'tipo'));

        $fontes = array_column($response->json('tutela_administrativa.fontes'), 'fonte');
        $this->assertContains('neighborhood_police_station', $fontes);
        $this->assertNotContains('jurisdictions', $fontes);
        $this->assertContains(1, array_column($response->json('tutela_administrativa.estacoes'), 'police_station_id'));
    }

    public function test_rota_responsavel_separa_responsabilidade_territorial_de_tutela_administrativa(): void
    {
        $point = DB::selectOne('
            SELECT
                ST_Y(ST_PointOnSurface(geom4326)) AS latitude,
                ST_X(ST_PointOnSurface(geom4326)) AS longitude
            FROM (
                SELECT CASE WHEN ST_SRID(aa.geom) = 4326 THEN aa.geom ELSE ST_Transform(aa.geom, 4326) END AS geom4326
                FROM "Area de Actuacao" aa
                WHERE aa.id = 6 AND aa.geom IS NOT NULL AND ST_IsValid(aa.geom)
            ) AS normalized
        ');

        $this->assertNotNull($point);

        $response = $this->getJson('/api/responsible-station?lat='.$point->latitude.'&lng='.$point->longitude);
        $response->assertOk();

        $this->assertArrayHasKey('station', $response->json());
        $this->assertSame(10, $response->json('responsabilidade_territorial.police_station_id'));
        $this->assertSame(6, $response->json('responsabilidade_territorial.fonte.source_id'));
        $this->assertContains(7, array_column($response->json('tutela_administrativa.estacoes'), 'police_station_id'));
        $this->assertSame('jurisdictions', $response->json('tutela_administrativa.fontes.0.fonte'));
        $this->assertArrayNotHasKey('source_document', $response->json());
    }
}
