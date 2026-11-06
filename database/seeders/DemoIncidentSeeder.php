<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoIncidentSeeder extends Seeder
{
    private const REFERENCE_PREFIX = 'DEMO-SMP-';
    private const MATOLA_A_BAIRRO = 'Matola A';
    private const MATOLA_A_CANONICAL_AREA_ID = 1;

    private const DISTRIBUTION = [
        'Matola A' => 12,
        'Fomento' => 9,
        'Liberdade' => 11,
        'Malhampsene' => 8,
        'Matola B' => 12,
        'Matola C' => 10,
        'Matola D' => 11,
        'Matola F' => 7,
        'Matola G' => 8,
        'Matola H' => 9,
        'Matola J' => 12,
        'Mussumbuluco' => 11,
        'Cikwama' => 10,
    ];

    private const LANDMARKS = [
        'Matola A' => 'Mercado da Matola',
        'Fomento' => 'Estação do Fomento',
        'Liberdade' => 'Mercado da Liberdade',
        'Malhampsene' => 'Escola Secundária de Malhampsene',
        'Matola B' => 'Zona do Cinema 700',
        'Matola C' => 'Avenida das Acácias',
        'Matola D' => 'Avenida das Indústrias',
        'Matola F' => 'Parque Industrial',
        'Matola G' => 'Estrada Nacional 1',
        'Matola H' => 'Mercado de Matola H',
        'Matola J' => 'Cruzamento da Matola J',
        'Mussumbuluco' => 'Estação de Mussumbuluco',
        'Cikwama' => 'Mercado de Cikwama',
    ];

    public function run(): void
    {
        $distribution = self::DISTRIBUTION;

        $this->assertBairrosAvailable($distribution);

        $expectedStations = $this->resolveExpectedStations($distribution);
        $planned = $this->buildPlan($distribution);

        $existingCodes = DB::table('incidents')
            ->where('reference_code', 'like', self::REFERENCE_PREFIX . '%')
            ->pluck('reference_code')
            ->all();
        $existing = array_flip($existingCodes);

        $missingByBairro = [];

        foreach ($planned as $entry) {
            if (!isset($existing[$entry['reference_code']])) {
                $missingByBairro[$entry['bairro']][] = $entry;
            }
        }

        $categories = Category::query()->pluck('id', 'name')->all();

        if (empty($categories)) {
            throw new RuntimeException('A tabela categories está vazia. Execute o CategorySeeder antes do DemoIncidentSeeder.');
        }

        $fallbackCategoryId = $categories['Outro'] ?? array_values($categories)[0];

        $users = User::query()
            ->whereIn('email', ['gestor@matolac.mz', 'agente@matolac.mz', 'cidadao@email.mz'])
            ->get()
            ->keyBy('email');

        $citizen = $users->get('cidadao@email.mz');
        $officer = $users->get('agente@matolac.mz');
        $manager = $users->get('gestor@matolac.mz');

        $fallbackReporterId = $citizen?->id ?? $manager?->id ?? $officer?->id;
        $fallbackAssigneeId = $officer?->id ?? $manager?->id;

        $scenarios = $this->scenarios();
        $scenarioCount = count($scenarios);

        $inserted = DB::transaction(function () use (
            $expectedStations,
            $missingByBairro,
            $distribution,
            $categories,
            $fallbackCategoryId,
            $fallbackReporterId,
            $fallbackAssigneeId,
            $scenarios,
            $scenarioCount
        ): int {
            $inserted = 0;

            foreach ($missingByBairro as $bairro => $entries) {
                $points = $this->generatePoints($bairro, count($entries));

                foreach ($entries as $index => $entry) {
                    $scenario = $scenarios[($entry['sequence'] - 1) % $scenarioCount];
                    $categoryId = $categories[$scenario['category']] ?? $fallbackCategoryId;
                    $status = $scenario['status'];
                    $daysAgo = match ($status) {
                        'pending' => rand(1, 20),
                        'investigating' => rand(5, 45),
                        'resolved' => rand(20, 95),
                        'archived' => rand(60, 200),
                    };

                    $incidentDate = now()
                        ->subDays($daysAgo)
                        ->setTime(rand(0, 23), rand(0, 59), rand(0, 59));

                    $resolvedAt = in_array($status, ['resolved', 'archived'], true)
                        ? $incidentDate->copy()->addDays(rand(1, 7))
                        : null;

                    $isAnonymous = $entry['sequence'] % 9 === 0;
                    $reportedBy = $isAnonymous ? null : $fallbackReporterId;
                    $assignedTo = in_array($status, ['investigating', 'resolved', 'archived'], true)
                        ? $fallbackAssigneeId
                        : null;

                    $stationId = $expectedStations[$bairro]['station_id'];
                    $point = $points[$index];

                    $incidentId = DB::table('incidents')->insertGetId([
                        'reference_code' => $entry['reference_code'],
                        'title' => $scenario['title'] . ' - ' . $bairro,
                        'description' => $scenario['description'] . ' Ocorrência registada em ' . $bairro . '.',
                        'category_id' => $categoryId,
                        'priority' => $scenario['priority'],
                        'status' => $status,
                        'latitude' => $point['lat'],
                        'longitude' => $point['lng'],
                        'address_detail' => 'Próximo de ' . self::LANDMARKS[$bairro] . ', ' . $bairro,
                        'neighborhood' => $bairro,
                        'reported_by' => $reportedBy,
                        'assigned_to' => $assignedTo,
                        'police_station_id' => $stationId,
                        'incident_date' => $incidentDate,
                        'resolved_at' => $resolvedAt,
                        'is_anonymous' => $isAnonymous,
                        'is_public' => true,
                        'created_at' => $incidentDate,
                        'updated_at' => $resolvedAt ?? $incidentDate,
                    ]);

                    if ($stationId === null) {
                        DB::table('incidents')
                            ->where('id', $incidentId)
                            ->update(['police_station_id' => null]);
                    }

                    $inserted++;
                }
            }

            $this->normalizeDemoStations($expectedStations);
            $this->validateDemoDataset($distribution, $expectedStations);

            return $inserted;
        });

        $total = array_sum($distribution);

        if ($inserted === 0) {
            $this->command?->info("DemoIncidentSeeder: as {$total} ocorrências demo já existem; nada foi inserido.");
        } else {
            $this->command?->info("DemoIncidentSeeder: {$inserted} ocorrências demo inseridas. Total demo: {$total}.");
        }
    }

    private function assertBairrosAvailable(array $distribution): void
    {
        $rows = DB::select('
            SELECT TRIM("Bairro") AS bairro
            FROM "Area de Estudo"
            WHERE geom IS NOT NULL
              AND ST_IsValid(geom)
            GROUP BY TRIM("Bairro")
        ');

        $available = [];

        foreach ($rows as $row) {
            $available[$row->bairro] = true;
        }

        foreach (array_keys($distribution) as $bairro) {
            if (!isset($available[$bairro])) {
                throw new RuntimeException("O bairro \"{$bairro}\" não existe em \"Area de Estudo\" com geometria válida.");
            }
        }
    }

    private function resolveExpectedStations(array $distribution): array
    {
        $rows = DB::select('
            SELECT TRIM(ae."Bairro") AS bairro, j.police_station_id
            FROM jurisdictions j
            INNER JOIN "Area de Estudo" ae ON ae.id = j.area_estudo_id
            WHERE j.is_active = true
              AND ae.geom IS NOT NULL
              AND ST_IsValid(ae.geom)
              AND (TRIM(ae."Bairro") <> ? OR ae.id = ?)
        ', [self::MATOLA_A_BAIRRO, self::MATOLA_A_CANONICAL_AREA_ID]);

        $stationsByBairro = array_fill_keys(array_keys($distribution), []);

        foreach ($rows as $row) {
            if (!array_key_exists($row->bairro, $stationsByBairro)) {
                continue;
            }

            $stationsByBairro[$row->bairro][] = (int) $row->police_station_id;
        }

        $expected = [];

        foreach ($stationsByBairro as $bairro => $stationIds) {
            $uniqueStations = array_values(array_unique($stationIds));
            $stationId = count($uniqueStations) === 1 ? $uniqueStations[0] : null;

            $expected[$bairro] = [
                'station_id' => $stationId,
                'station_count' => count($uniqueStations),
            ];

            if (count($uniqueStations) === 0) {
                $this->command?->warn("DemoIncidentSeeder: nenhuma jurisdição activa para {$bairro}; as ocorrências ficarão sem esquadra.");
            } elseif (count($uniqueStations) > 1) {
                $ids = implode(', ', $uniqueStations);
                $this->command?->warn("DemoIncidentSeeder: conflito de jurisdição em {$bairro} ({$ids}); as ocorrências ficarão sem esquadra.");
            }
        }

        return $expected;
    }

    private function buildPlan(array $distribution): array
    {
        $plan = [];
        $sequence = 1;

        foreach ($distribution as $bairro => $count) {
            for ($index = 0; $index < $count; $index++) {
                $plan[] = [
                    'sequence' => $sequence,
                    'reference_code' => sprintf('%s%04d', self::REFERENCE_PREFIX, $sequence),
                    'bairro' => $bairro,
                    'local_index' => $index,
                ];

                $sequence++;
            }
        }

        return $plan;
    }

    private function generatePoints(string $bairro, int $count): array
    {
        $bounds = config('map.bounds');

        if (!$bounds) {
            throw new RuntimeException('A configuração map.bounds não está definida.');
        }

        $southLat = $bounds['south_west']['lat'];
        $northLat = $bounds['north_east']['lat'];
        $westLng = $bounds['south_west']['lng'];
        $eastLng = $bounds['north_east']['lng'];

        foreach ([50, 150, 400] as $multiplier) {
            $candidateCount = max($count * $multiplier, 100);

            $rows = DB::select("
                WITH bairro AS (
                    SELECT ST_Union(ST_CollectionExtract(geom, 3)) AS geom
                    FROM \"Area de Estudo\"
                    WHERE TRIM(\"Bairro\") = ?
                      AND geom IS NOT NULL
                      AND ST_IsValid(geom)
                ),
                generated AS (
                    SELECT (ST_Dump(ST_GeneratePoints(b.geom, {$candidateCount}))).geom AS geom
                    FROM bairro b
                    WHERE b.geom IS NOT NULL
                ),
                candidates AS (
                    SELECT ST_SetSRID(
                        ST_MakePoint(
                            ROUND(ST_X(g.geom)::numeric, 8)::double precision,
                            ROUND(ST_Y(g.geom)::numeric, 8)::double precision
                        ),
                        4326
                    ) AS geom
                    FROM generated g
                    WHERE g.geom IS NOT NULL
                )
                SELECT DISTINCT
                    ST_Y(c.geom) AS lat,
                    ST_X(c.geom) AS lng
                FROM candidates c
                CROSS JOIN bairro b
                WHERE ST_Covers(b.geom, c.geom)
                  AND ST_Y(c.geom) BETWEEN ? AND ?
                  AND ST_X(c.geom) BETWEEN ? AND ?
                  AND NOT EXISTS (
                      SELECT 1
                      FROM incidents i
                      WHERE i.geometry IS NOT NULL
                        AND ST_Equals(i.geometry, c.geom)
                  )
                LIMIT {$count}
            ", [$bairro, $southLat, $northLat, $westLng, $eastLng]);

            if (count($rows) === $count) {
                return array_map(
                    fn ($row) => ['lat' => (float) $row->lat, 'lng' => (float) $row->lng],
                    $rows
                );
            }
        }

        throw new RuntimeException("Não foi possível gerar {$count} pontos válidos para o bairro {$bairro}. Verifique a geometria em \"Area de Estudo\" e os limites em map.bounds.");
    }

    private function normalizeDemoStations(array $expectedStations): void
    {
        foreach ($expectedStations as $bairro => $expected) {
            $query = DB::table('incidents')
                ->where('reference_code', 'like', self::REFERENCE_PREFIX . '%')
                ->where('neighborhood', $bairro);

            if ($expected['station_id'] === null) {
                $query->whereNotNull('police_station_id')
                    ->update(['police_station_id' => null]);

                continue;
            }

            $query->where(function ($query) use ($expected) {
                $query->whereNull('police_station_id')
                    ->orWhere('police_station_id', '!=', $expected['station_id']);
            })->update(['police_station_id' => $expected['station_id']]);
        }
    }

    private function validateDemoDataset(array $distribution, array $expectedStations): void
    {
        $expectedTotal = array_sum($distribution);
        $prefix = self::REFERENCE_PREFIX . '%';

        $softDeleted = DB::table('incidents')
            ->where('reference_code', 'like', $prefix)
            ->whereNotNull('deleted_at')
            ->count();

        if ($softDeleted > 0) {
            throw new RuntimeException("Existem {$softDeleted} ocorrências demo eliminadas logicamente (soft delete). Restaure-as ou remova-as definitivamente antes de executar novamente o seeder.");
        }

        $total = DB::table('incidents')
            ->where('reference_code', 'like', $prefix)
            ->count();

        if ($total !== $expectedTotal) {
            throw new RuntimeException("O conjunto demo deveria ter {$expectedTotal} ocorrências, mas contém {$total}.");
        }

        $invalidGeometry = DB::selectOne('
            SELECT COUNT(*) AS total
            FROM incidents
            WHERE reference_code LIKE ?
              AND (
                  geometry IS NULL
                  OR ST_SRID(geometry) <> 4326
                  OR GeometryType(geometry) <> \'POINT\'
                  OR NOT ST_IsValid(geometry)
              )
        ', [$prefix]);

        if ((int) $invalidGeometry->total > 0) {
            throw new RuntimeException("Existem {$invalidGeometry->total} ocorrências demo com geometria inválida (esperado POINT válido em SRID 4326).");
        }

        $invalidCoordinates = DB::selectOne('
            SELECT COUNT(*) AS total
            FROM incidents
            WHERE reference_code LIKE ?
              AND (latitude IS NULL OR longitude IS NULL)
        ', [$prefix]);

        if ((int) $invalidCoordinates->total > 0) {
            throw new RuntimeException("Existem {$invalidCoordinates->total} ocorrências demo sem latitude ou longitude.");
        }

        $invalidIncidentDate = DB::selectOne('
            SELECT COUNT(*) AS total
            FROM incidents
            WHERE reference_code LIKE ?
              AND (incident_date IS NULL OR incident_date > NOW())
        ', [$prefix]);

        if ((int) $invalidIncidentDate->total > 0) {
            throw new RuntimeException("Existem {$invalidIncidentDate->total} ocorrências demo sem data ou com data futura.");
        }

        $invalidResolvedAt = DB::selectOne('
            SELECT COUNT(*) AS total
            FROM incidents
            WHERE reference_code LIKE ?
              AND (
                  (status IN (\'resolved\', \'archived\') AND (resolved_at IS NULL OR resolved_at < incident_date OR resolved_at > NOW()))
                  OR
                  (status IN (\'pending\', \'investigating\') AND resolved_at IS NOT NULL)
              )
        ', [$prefix]);

        if ((int) $invalidResolvedAt->total > 0) {
            throw new RuntimeException("Existem {$invalidResolvedAt->total} ocorrências demo com resolved_at incoerente com o estado.");
        }

        $nonPublic = DB::selectOne('
            SELECT COUNT(*) AS total
            FROM incidents
            WHERE reference_code LIKE ?
              AND (is_public = false OR is_public IS NULL)
        ', [$prefix]);

        if ((int) $nonPublic->total > 0) {
            throw new RuntimeException("Existem {$nonPublic->total} ocorrências demo não públicas; todas as 130 devem ser visíveis no mapa.");
        }

        $outsideBairro = DB::selectOne('
            SELECT COUNT(*) AS total
            FROM incidents i
            WHERE i.reference_code LIKE ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM "Area de Estudo" ae
                  WHERE TRIM(ae."Bairro") = i.neighborhood
                    AND ae.geom IS NOT NULL
                    AND ST_IsValid(ae.geom)
                    AND ST_Covers(ae.geom, i.geometry)
              )
        ', [$prefix]);

        if ((int) $outsideBairro->total > 0) {
            throw new RuntimeException("Existem {$outsideBairro->total} ocorrências demo fora do polígono do bairro indicado.");
        }

        $bounds = config('map.bounds');

        if ($bounds) {
            $outsideBounds = DB::selectOne('
                SELECT COUNT(*) AS total
                FROM incidents
                WHERE reference_code LIKE ?
                  AND (
                      latitude NOT BETWEEN ? AND ?
                      OR longitude NOT BETWEEN ? AND ?
                  )
            ', [
                $prefix,
                $bounds['south_west']['lat'],
                $bounds['north_east']['lat'],
                $bounds['south_west']['lng'],
                $bounds['north_east']['lng'],
            ]);

            if ((int) $outsideBounds->total > 0) {
                throw new RuntimeException("Existem {$outsideBounds->total} ocorrências demo fora dos limites de map.bounds.");
            }
        }

        $rows = DB::select('
            SELECT
                neighborhood AS bairro,
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE is_public) AS public_total
            FROM incidents
            WHERE reference_code LIKE ?
            GROUP BY neighborhood
        ', [$prefix]);

        $byBairro = [];

        foreach ($rows as $row) {
            $byBairro[$row->bairro] = [
                'total' => (int) $row->total,
                'public_total' => (int) $row->public_total,
            ];
        }

        foreach ($distribution as $bairro => $expectedCount) {
            if (!isset($byBairro[$bairro]) || $byBairro[$bairro]['total'] !== $expectedCount) {
                $actual = $byBairro[$bairro]['total'] ?? 0;
                throw new RuntimeException("O bairro {$bairro} deveria ter {$expectedCount} ocorrências demo, mas tem {$actual}.");
            }

            if ($byBairro[$bairro]['public_total'] < min(2, $expectedCount)) {
                throw new RuntimeException("O bairro {$bairro} não tem ocorrências demo públicas suficientes para o mapa.");
            }
        }

        foreach ($expectedStations as $bairro => $expected) {
            $stationRows = DB::select('
                SELECT DISTINCT police_station_id
                FROM incidents
                WHERE reference_code LIKE ?
                  AND neighborhood = ?
            ', [$prefix, $bairro]);

            $actual = [];

            foreach ($stationRows as $row) {
                $actual[] = $row->police_station_id === null ? 'null' : (string) (int) $row->police_station_id;
            }

            $actual = array_values(array_unique($actual));
            $expectedKey = $expected['station_id'] === null ? 'null' : (string) $expected['station_id'];

            if ($actual !== [$expectedKey]) {
                $found = empty($actual) ? 'nenhuma' : implode(', ', $actual);
                throw new RuntimeException("Jurisdição incoerente em {$bairro}: esperado {$expectedKey}, encontrado {$found}.");
            }
        }
    }

    private function scenarios(): array
    {
        return [
            [
                'category' => 'Roubo',
                'title' => 'Roubo a residência',
                'description' => 'Indivíduos armados entraram numa residência e levaram electrodomésticos e dinheiro.',
                'priority' => 'high',
                'status' => 'investigating',
            ],
            [
                'category' => 'Furto',
                'title' => 'Furto de bicicleta',
                'description' => 'Uma bicicleta foi subtraída durante a noite junto a uma residência.',
                'priority' => 'low',
                'status' => 'pending',
            ],
            [
                'category' => 'Acidente de Viação',
                'title' => 'Colisão na via principal',
                'description' => 'Dois veículos colidiram na via principal e provocaram feridos ligeiros.',
                'priority' => 'high',
                'status' => 'investigating',
            ],
            [
                'category' => 'Vandalismo',
                'title' => 'Vandalismo em espaço público',
                'description' => 'Equipamentos de um espaço público foram danificados com pichações.',
                'priority' => 'medium',
                'status' => 'pending',
            ],
            [
                'category' => 'Violência Doméstica',
                'title' => 'Violência doméstica reportada',
                'description' => 'Uma vítima procurou apoio policial após episódios de agressão no ambiente familiar.',
                'priority' => 'high',
                'status' => 'investigating',
            ],
            [
                'category' => 'Incêndio',
                'title' => 'Incêndio em terreno baldio',
                'description' => 'Fogo em vegetação seca assustou moradores e foi controlado pelos bombeiros.',
                'priority' => 'high',
                'status' => 'resolved',
            ],
            [
                'category' => 'Perturbação da Ordem Pública',
                'title' => 'Ruído excessivo durante a noite',
                'description' => 'Moradores reportaram música em volume excessivo após as 22 horas.',
                'priority' => 'low',
                'status' => 'resolved',
            ],
            [
                'category' => 'Pessoa Desaparecida',
                'title' => 'Pessoa desaparecida',
                'description' => 'Uma pessoa não regressou a casa e a família pediu apoio às autoridades.',
                'priority' => 'high',
                'status' => 'investigating',
            ],
            [
                'category' => 'Desastre Natural',
                'title' => 'Inundação após chuvas intensas',
                'description' => 'Chuvas fortes provocaram inundações em casas e vias de circulação.',
                'priority' => 'urgent',
                'status' => 'archived',
            ],
            [
                'category' => 'Fraude',
                'title' => 'Fraude em transferência bancária',
                'description' => 'Uma vítima transferiu dinheiro para uma conta fraudulenta após contacto telefónico.',
                'priority' => 'medium',
                'status' => 'investigating',
            ],
            [
                'category' => 'Agressão Física',
                'title' => 'Agressão física em local público',
                'description' => 'Uma discussão terminou em agressão física junto a um espaço comercial.',
                'priority' => 'medium',
                'status' => 'resolved',
            ],
            [
                'category' => 'Homicídio',
                'title' => 'Homicídio em investigação',
                'description' => 'A polícia isolou a área e iniciou diligências após a descoberta de um corpo.',
                'priority' => 'urgent',
                'status' => 'archived',
            ],
            [
                'category' => 'Tráfico de Drogas',
                'title' => 'Tráfico de drogas',
                'description' => 'Movimentação suspeita levou a diligências e à apreensão de substâncias ilícitas.',
                'priority' => 'high',
                'status' => 'investigating',
            ],
            [
                'category' => 'Violação',
                'title' => 'Violação reportada',
                'description' => 'A vítima foi encaminhada para apoio e a polícia iniciou a investigação do caso.',
                'priority' => 'urgent',
                'status' => 'archived',
            ],
            [
                'category' => 'Outro',
                'title' => 'Ocorrência diversa',
                'description' => 'Situação não categorizada foi registada para avaliação e encaminhamento.',
                'priority' => 'low',
                'status' => 'pending',
            ],
            [
                'category' => 'Roubo',
                'title' => 'Tentativa de roubo a estabelecimento',
                'description' => 'Dois indivíduos tentaram assaltar um estabelecimento mas foram impedidos.',
                'priority' => 'high',
                'status' => 'pending',
            ],
            [
                'category' => 'Furto',
                'title' => 'Furto de material de construção',
                'description' => 'Material de construção foi subtraído de uma obra durante a madrugada.',
                'priority' => 'low',
                'status' => 'archived',
            ],
            [
                'category' => 'Acidente de Viação',
                'title' => 'Acidente com feridos ligeiros',
                'description' => 'Um acidente entre um automóvel e uma mota provocou feridos ligeiros.',
                'priority' => 'medium',
                'status' => 'resolved',
            ],
            [
                'category' => 'Vandalismo',
                'title' => 'Danos em equipamento público',
                'description' => 'Iluminação pública e sinalização foram danificadas por actos de vandalismo.',
                'priority' => 'medium',
                'status' => 'resolved',
            ],
            [
                'category' => 'Fraude',
                'title' => 'Golpe de investimento',
                'description' => 'Promessa de retorno financeiro elevado levou uma vítima a perder dinheiro.',
                'priority' => 'medium',
                'status' => 'resolved',
            ],
        ];
    }
}
