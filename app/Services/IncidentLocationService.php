<?php

namespace App\Services;

use App\Models\Incident;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IncidentLocationService
{
    public function __construct(
        private readonly IncidentJurisdictionService $jurisdictionService,
        private readonly AreaAtuacaoService $areaAtuacaoService
    ) {
    }

    public function normalize(mixed $latitude, mixed $longitude): array
    {
        return [
            'latitude' => $this->normalizeCoordinate($latitude, 'latitude', -90.0, 90.0),
            'longitude' => $this->normalizeCoordinate($longitude, 'longitude', -180.0, 180.0),
        ];
    }

    public function resolve(mixed $latitude, mixed $longitude): array
    {
        $coordinates = $this->normalize($latitude, $longitude);

        $jurisdiction = $this->jurisdictionService->determinePoliceStationFromGeometry(
            $coordinates['longitude'],
            $coordinates['latitude']
        );

        $area = $this->areaAtuacaoService->findAtPoint(
            $coordinates['latitude'],
            $coordinates['longitude']
        );

        $bairro = $this->resolveBairro($jurisdiction, $area);
        $status = $this->resolveStatus($area, $jurisdiction);
        $station = $this->resolveStation($area);

        return [
            'status' => $status,
            'coordinates' => $coordinates,
            'bairro' => $bairro,
            'area_de_atuacao_id' => $station['area_de_atuacao_id'],
            'area_de_atuacao_ids' => $station['area_de_atuacao_ids'],
            'police_station_id' => $station['police_station_id'],
            'police_station_name' => $station['police_station_name'],
            'jurisdiction' => [
                'status' => $jurisdiction['status'] ?? null,
                'conflict' => (bool) ($jurisdiction['conflict'] ?? false),
                'police_station_id' => $jurisdiction['police_station_id'] ?? null,
                'area_estudo_id' => $jurisdiction['area_estudo_id'] ?? null,
            ],
            'tutela_administrativa' => $area['tutela_administrativa'] ?? [],
            'avisos' => $area['avisos'] ?? [],
        ];
    }

    public function bairroIdentificado(array $resolution): ?string
    {
        $bairro = $resolution['bairro'] ?? null;

        if ($bairro === null || trim((string) $bairro) === '') {
            return null;
        }

        return trim((string) $bairro);
    }

    public function ensureNoStationForUnresolved(Incident $incident, array $resolution): void
    {
        if (($resolution['police_station_id'] ?? null) !== null) {
            return;
        }

        DB::table($incident->getTable())
            ->where($incident->getKeyName(), $incident->getKey())
            ->update(['police_station_id' => null]);

        $incident->forceFill(['police_station_id' => null])
            ->syncOriginalAttribute('police_station_id');
    }

    private function normalizeCoordinate(mixed $value, string $field, float $min, float $max): float
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            throw new InvalidArgumentException("A {$field} é obrigatória.");
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("A {$field} deve ser um número válido.");
        }

        $number = (float) $value;

        if (! is_finite($number)) {
            throw new InvalidArgumentException("A {$field} deve ser um número válido.");
        }

        if ($number < $min || $number > $max) {
            throw new InvalidArgumentException("A {$field} deve estar entre {$min} e {$max}.");
        }

        return $number;
    }

    private function resolveBairro(array $jurisdiction, array $area): ?string
    {
        $bairro = $jurisdiction['bairro'] ?? null;

        if (($bairro === null || trim((string) $bairro) === '') && isset($area['areas'][0]['bairro'])) {
            $bairro = $area['areas'][0]['bairro'];
        }

        if ($bairro === null) {
            return null;
        }

        $bairro = trim((string) $bairro);

        return $bairro === '' ? null : $bairro;
    }

    private function resolveStatus(array $area, array $jurisdiction): string
    {
        $areaStatus = $area['status'] ?? 'not_found';

        if ($areaStatus === 'ambiguous') {
            return 'area_atuacao_ambigua';
        }

        if ($areaStatus === 'not_found') {
            return ($jurisdiction['bairro'] ?? null) === null
                ? 'fora_area_estudo'
                : 'area_estudo_sem_area_atuacao';
        }

        $station = $this->resolveStation($area);

        if ($station['police_station_id'] === null) {
            return 'sem_esquadra_territorial';
        }

        $types = array_column($area['avisos'] ?? [], 'tipo');

        return array_intersect($types, [
            'divergencia_administrativa',
            'informacao_pendente_confirmacao',
        ]) !== []
            ? 'territorial_resolvido_pendente_validacao'
            : 'territorial_resolvido';
    }

    private function resolveStation(array $area): array
    {
        $areas = $area['areas'] ?? [];
        $station = $area['responsabilidade_territorial'] ?? null;

        if (($area['status'] ?? null) === 'matched' && is_array($station)) {
            return [
                'area_de_atuacao_id' => $station['area_de_atuacao_id'] ?? null,
                'area_de_atuacao_ids' => $station['area_de_atuacao_id'] === null
                    ? []
                    : [$station['area_de_atuacao_id']],
                'police_station_id' => $station['police_station_id'] ?? null,
                'police_station_name' => $station['police_station_name'] ?? null,
            ];
        }

        return [
            'area_de_atuacao_id' => null,
            'area_de_atuacao_ids' => array_values(array_map(
                static fn (array $item): int => (int) ($item['id'] ?? 0),
                $areas
            )),
            'police_station_id' => null,
            'police_station_name' => null,
        ];
    }
}
