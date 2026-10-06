<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IncidentJurisdictionService
 *
 * Determina a esquadra responsável por uma ocorrência com base na jurisdição espacial.
 *
 * Fluxo de determinação:
 *   1. O ponto (longitude, latitude) da ocorrência é verificado contra a tabela "Area de Estudo" usando ST_Covers.
 *   2. Se o ponto estiver dentro de um bairro, são procuradas jurisdições ativas na tabela jurisdictions.
 *   3. Se existir exactamente uma jurisdição ativa → status = resolved → retorna police_station_id.
 *   4. Se existirem múltiplas jurisdições activas → status = conflict → police_station_id = NULL.
 *   5. Se não existir jurisdição activa → status = no_active_jurisdiction → police_station_id = NULL.
 *   6. Se o ponto não estiver em nenhum bairro → status = bairro_not_found → police_station_id = NULL.
 *
 * MATOLA D — SITUAÇÃO DE CONFLITO JURISDICIONAL
 *
 * Matola D possui actualmente mais de uma jurisdição policial activa associada ao bairro.
 * Como não foi encontrada, nesta fase, uma delimitação espacial oficial suficientemente
 * confiável que permita separar as duas áreas, o sistema mantém o resultado como conflito
 * e não realiza atribuição automática da ocorrência a uma esquadra.
 *
 * As duas jurisdições activas para Matola D são:
 *   - 1ª Esquadra PRM Matola (police_station_id = 2)
 *   - 2ª Esquadra PRM Matola (police_station_id = 10)
 *
 * NOTA IMPORTANTE: O sistema NÃO tenta determinar automaticamente qual é a rua limite.
 * Uma futura alteração de Matola D só deve ser feita quando existir uma fonte de confirmação
 * suficiente (documento oficial da PRM, mapa oficial, regulamento, informação formal do
 * comando, ou confirmação de campo devidamente documentada). A fonte deve ser registada em
 * jurisdictions.source_document e, quando apropriado, em effective_date e description.
 *
 * A arquitetura actual permite que o conflito seja resolvido futuramente sem reconstruir o
 * sistema: basta actualizar as geometrias em jurisdictions.geometry e validar os resultados.
 */
class IncidentJurisdictionService
{
    /**
     * Determine the police station responsible for an incident based on spatial jurisdiction.
     *
     * Logic chain:
     *   incident geometry (point, SRID 4326)
     *   → ST_Covers("Area de Estudo".geom, point) → bairro
     *   → jurisdictions (is_active=true, area_estudo_id) → police_station_id
     *
     * @param float $longitude
     * @param float $latitude
     * @return array{success: bool, police_station_id: ?int, area_estudo_id: ?int, bairro: ?string, jurisdiction_id: ?int, jurisdiction_name: ?string, station_name: ?string, status: string, message: string, conflict: bool, multiple_jurisdictions: array}
     */
    public function determinePoliceStationFromGeometry(float $longitude, float $latitude): array
    {
        $pointWkt = "SRID=4326;POINT({$longitude} {$latitude})";

        $bairro = $this->findBairro($longitude, $latitude);

        if ($bairro === null) {
            Log::info('Jurisdiction lookup: bairro not found', [
                'lat' => $latitude,
                'lng' => $longitude,
            ]);

            return [
                'success' => false,
                'police_station_id' => null,
                'area_estudo_id' => null,
                'bairro' => null,
                'jurisdiction_id' => null,
                'jurisdiction_name' => null,
                'station_name' => null,
                'status' => 'bairro_not_found',
                'message' => 'A geometria da ocorrência não corresponde a nenhum bairro na Área de Estudo.',
                'conflict' => false,
                'multiple_jurisdictions' => [],
            ];
        }

        $jurisdictions = $this->findActiveJurisdictions($bairro['id']);

        if ($jurisdictions->isEmpty()) {
            Log::info('Jurisdiction lookup: no active jurisdiction for bairro', [
                'bairro_id' => $bairro['id'],
                'bairro_name' => $bairro['bairro'],
            ]);

            return [
                'success' => false,
                'police_station_id' => null,
                'area_estudo_id' => $bairro['id'],
                'bairro' => $bairro['bairro'],
                'jurisdiction_id' => null,
                'jurisdiction_name' => null,
                'station_name' => null,
                'status' => 'no_active_jurisdiction',
                'message' => "O bairro \"{$bairro['bairro']}\" não possui jurisdição policial ativa.",
                'conflict' => false,
                'multiple_jurisdictions' => [],
            ];
        }

        if ($jurisdictions->count() > 1) {
            $conflictData = $jurisdictions->map(fn($j) => [
                'jurisdiction_id' => $j->id,
                'jurisdiction_name' => $j->name,
                'police_station_id' => $j->police_station_id,
                'station_name' => $j->station_name,
            ])->toArray();

            Log::warning('Jurisdiction lookup: multiple active jurisdictions for same bairro', [
                'bairro_id' => $bairro['id'],
                'bairro_name' => $bairro['bairro'],
                'jurisdiction_count' => $jurisdictions->count(),
                'jurisdictions' => $conflictData,
            ]);

            return [
                'success' => false,
                'police_station_id' => null,
                'area_estudo_id' => $bairro['id'],
                'bairro' => $bairro['bairro'],
                'jurisdiction_id' => null,
                'jurisdiction_name' => null,
                'station_name' => null,
                'status' => 'conflict',
                'message' => "O bairro \"{$bairro['bairro']}\" possui múltiplas jurisdições ativas (conflito).",
                'conflict' => true,
                'multiple_jurisdictions' => $conflictData,
            ];
        }

        $jurisdiction = $jurisdictions->first();

        Log::info('Jurisdiction lookup: success', [
            'bairro' => $bairro['bairro'],
            'jurisdiction_id' => $jurisdiction->id,
            'police_station_id' => $jurisdiction->police_station_id,
            'station_name' => $jurisdiction->station_name,
        ]);

        return [
            'success' => true,
            'police_station_id' => (int) $jurisdiction->police_station_id,
            'area_estudo_id' => (int) $bairro['id'],
            'bairro' => $bairro['bairro'],
            'jurisdiction_id' => (int) $jurisdiction->id,
            'jurisdiction_name' => $jurisdiction->name,
            'station_name' => $jurisdiction->station_name,
            'status' => 'resolved',
            'message' => "Ocorrência atribuída a \"{$jurisdiction->station_name}\" via jurisdição do bairro \"{$bairro['bairro']}\".",
            'conflict' => false,
            'multiple_jurisdictions' => [],
        ];
    }

    /**
     * Find the bairro (Area de Estudo) that contains the given point.
     *
     * Uses ST_Covers to check if the bairro polygon covers the incident point.
     * Handles the duplicate "Matola A" (id 14) — returns the first match.
     */
    private function findBairro(float $longitude, float $latitude): ?array
    {
        $results = DB::select('
            SELECT id, "Bairro" AS bairro
            FROM "Area de Estudo"
            WHERE ST_Covers(geom, ST_SetSRID(ST_MakePoint(?, ?), 4326))
            LIMIT 2
        ', [$longitude, $latitude]);

        if (empty($results)) {
            return null;
        }

        return (array) $results[0];
    }

    /**
     * Find all active jurisdictions for a given area_estudo_id.
     *
     * @param int $areaEstudoId
     * @return \Illuminate\Support\Collection
     */
    private function findActiveJurisdictions(int $areaEstudoId): \Illuminate\Support\Collection
    {
        $results = DB::select('
            SELECT
                j.id,
                j.name,
                j.police_station_id,
                j.area_estudo_id,
                ps.name AS station_name
            FROM jurisdictions j
            LEFT JOIN police_stations ps ON ps.id = j.police_station_id
            WHERE j.area_estudo_id = ?
              AND j.is_active = true
        ', [$areaEstudoId]);

        return collect($results);
    }
}
