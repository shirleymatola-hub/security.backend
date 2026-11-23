<?php

namespace Tests\Unit;

use App\Services\AreaAtuacaoService;
use App\Services\IncidentJurisdictionService;
use App\Services\IncidentLocationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IncidentLocationServiceTest extends TestCase
{
    public function test_normalizes_valid_coordinates(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            ['latitude' => -25.9692, 'longitude' => 32.4539],
            $service->normalize(' -25.9692 ', '32.4539')
        );
    }

    public function test_rejects_missing_coordinates_without_using_map_defaults(): void
    {
        $service = $this->makeService();

        foreach ([[null, 32.4539], [-25.9692, null], ['', 32.4539], [-25.9692, '   ']] as [$latitude, $longitude]) {
            try {
                $service->resolve($latitude, $longitude);
                $this->fail('Coordenadas ausentes deveriam ser rejeitadas.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('obrigatória', $exception->getMessage());
            }
        }
    }

    public function test_rejects_non_numeric_and_out_of_range_coordinates(): void
    {
        $service = $this->makeService();

        foreach ([
            ['matola', 32.4539, 'número válido'],
            [-25.9692, 'leste', 'número válido'],
            [-91, 32.4539, 'entre -90 e 90'],
            [-25.9692, 181, 'entre -180 e 180'],
        ] as [$latitude, $longitude, $expected]) {
            try {
                $service->resolve($latitude, $longitude);
                $this->fail('Coordenadas inválidas deveriam ser rejeitadas.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString($expected, $exception->getMessage());
            }
        }
    }

    public function test_uses_bairro_from_official_geometry(): void
    {
        $jurisdiction = $this->jurisdictionResult('resolved', 'Matola A', 2);
        $area = $this->areaResult('matched', [$this->area(1, 'Matola A', 2)]);

        $result = $this->makeService($jurisdiction, $area)->resolve(-25.96, 32.45);

        $this->assertSame('Matola A', $result['bairro']);
        $this->assertSame('territorial_resolvido', $result['status']);
    }

    public function test_falls_back_to_area_bairro_when_jurisdiction_has_none(): void
    {
        $jurisdiction = $this->jurisdictionResult('bairro_not_found', null, null);
        $area = $this->areaResult('matched', [$this->area(9, 'Matola C', 6)]);

        $result = $this->makeService($jurisdiction, $area)->resolve(-25.96, 32.45);

        $this->assertSame('Matola C', $result['bairro']);
    }

    public function test_bairro_identificado_is_null_when_location_has_no_spatial_match(): void
    {
        $service = $this->makeService(
            $this->jurisdictionResult('bairro_not_found', null, null),
            $this->areaResult('not_found', [])
        );

        $resolution = $service->resolve(-25.50, 32.20);

        $this->assertNull($resolution['bairro']);
        $this->assertNull($service->bairroIdentificado($resolution));
        $this->assertSame('Matola C', $service->bairroIdentificado(['bairro' => ' Matola C ']));
    }

    public function test_assigns_station_from_single_matched_area(): void
    {
        $jurisdiction = $this->jurisdictionResult('resolved', 'Matola B', 7);
        $area = $this->areaResult('matched', [$this->area(12, 'Matola B', 7)]);

        $result = $this->makeService($jurisdiction, $area)->resolve(-25.96, 32.45);

        $this->assertSame(12, $result['area_de_atuacao_id']);
        $this->assertSame(7, $result['police_station_id']);
        $this->assertSame('territorial_resolvido', $result['status']);
    }

    public function test_matola_d_two_polygons_resolve_by_containing_polygon(): void
    {
        $jurisdiction = $this->jurisdictionResult('conflict', 'Matola D', null, true);

        $first = $this->makeService(
            $jurisdiction,
            $this->areaResult('matched', [$this->area(40, 'Matola D', 2)])
        )->resolve(-25.95, 32.44);
        $second = $this->makeService(
            $jurisdiction,
            $this->areaResult('matched', [$this->area(41, 'Matola D', 10)])
        )->resolve(-25.94, 32.43);

        $this->assertSame(40, $first['area_de_atuacao_id']);
        $this->assertSame(2, $first['police_station_id']);
        $this->assertSame(41, $second['area_de_atuacao_id']);
        $this->assertSame(10, $second['police_station_id']);
        $this->assertTrue($first['jurisdiction']['conflict']);
    }

    public function test_matola_j_keeps_pending_validation_status(): void
    {
        $jurisdiction = $this->jurisdictionResult('resolved', 'Matola J', 5);
        $areaData = $this->area(11, 'Matola J', 5);
        $areaData['avisos'] = [[
            'tipo' => 'informacao_pendente_confirmacao',
            'mensagem' => 'A tutela administrativa depende de validação documental.',
        ]];

        $result = $this->makeService($jurisdiction, $this->areaResult('matched', [$areaData]))
            ->resolve(-25.93, 32.42);

        $this->assertSame(5, $result['police_station_id']);
        $this->assertSame('territorial_resolvido_pendente_validacao', $result['status']);
    }

    public function test_outside_study_area_has_no_station(): void
    {
        $jurisdiction = $this->jurisdictionResult('bairro_not_found', null, null);
        $area = $this->areaResult('not_found', []);

        $result = $this->makeService($jurisdiction, $area)->resolve(-25.50, 32.20);

        $this->assertSame('fora_area_estudo', $result['status']);
        $this->assertNull($result['bairro']);
        $this->assertNull($result['police_station_id']);
    }

    public function test_missing_territorial_responsibility_does_not_use_jurisdiction_station(): void
    {
        $jurisdiction = $this->jurisdictionResult('resolved', 'Matola A', 2);
        $area = $this->areaResult('not_found', []);

        $result = $this->makeService($jurisdiction, $area)->resolve(-25.96, 32.45);

        $this->assertSame('area_estudo_sem_area_atuacao', $result['status']);
        $this->assertSame(2, $result['jurisdiction']['police_station_id']);
        $this->assertNull($result['police_station_id']);
    }

    public function test_ambiguous_area_has_no_station(): void
    {
        $jurisdiction = $this->jurisdictionResult('resolved', 'Bairro X', 3);
        $area = $this->areaResult('ambiguous', [
            $this->area(50, 'Bairro X', 3),
            $this->area(51, 'Bairro X', 4),
        ]);

        $result = $this->makeService($jurisdiction, $area)->resolve(-25.96, 32.45);

        $this->assertSame('area_atuacao_ambigua', $result['status']);
        $this->assertNull($result['police_station_id']);
        $this->assertSame([50, 51], $result['area_de_atuacao_ids']);
    }

    private function makeService(?array $jurisdiction = null, ?array $area = null): IncidentLocationService
    {
        $jurisdictionService = $this->createMock(IncidentJurisdictionService::class);
        $jurisdictionService->method('determinePoliceStationFromGeometry')
            ->willReturn($jurisdiction ?? $this->jurisdictionResult('bairro_not_found', null, null));

        $areaService = $this->createMock(AreaAtuacaoService::class);
        $areaService->method('findAtPoint')
            ->willReturn($area ?? $this->areaResult('not_found', []));

        return new IncidentLocationService($jurisdictionService, $areaService);
    }

    private function jurisdictionResult(
        string $status,
        ?string $bairro,
        ?int $stationId,
        bool $conflict = false
    ): array {
        return [
            'success' => $stationId !== null,
            'police_station_id' => $stationId,
            'area_estudo_id' => null,
            'bairro' => $bairro,
            'jurisdiction_id' => null,
            'jurisdiction_name' => null,
            'station_name' => null,
            'status' => $status,
            'message' => null,
            'conflict' => $conflict,
            'multiple_jurisdictions' => [],
        ];
    }

    private function area(int $id, string $bairro, ?int $stationId): array
    {
        return [
            'id' => $id,
            'bairro' => $bairro,
            'police_station_id' => $stationId,
            'police_station_name' => $stationId === null ? null : "Esquadra {$stationId}",
            'responsabilidade_territorial' => [
                'estado' => $stationId === null ? 'sem_esquadra_territorial' : 'definida',
                'police_station_id' => $stationId,
                'police_station_name' => $stationId === null ? null : "Esquadra {$stationId}",
                'area_de_atuacao_id' => $id,
            ],
            'avisos' => [],
        ];
    }

    private function areaResult(string $status, array $areas): array
    {
        $single = count($areas) === 1 ? $areas[0] : null;

        return [
            'status' => $status,
            'ambiguous' => count($areas) > 1,
            'areas' => $areas,
            'responsabilidade_territorial' => $single['responsabilidade_territorial'] ?? [
                'estado' => $areas === [] ? 'sem_area_operacional' : 'ambiguo',
                'areas' => [],
            ],
            'tutela_administrativa' => [],
            'avisos' => $single['avisos'] ?? [],
        ];
    }
}
