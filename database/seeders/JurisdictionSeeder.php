<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Jurisdiction;

class JurisdictionSeeder extends Seeder
{
    public function run(): void
    {
        $effectiveDate = '2026-09-06';

        $jurisdictions = [
            // =============================================
            // 1ª ESQUADRA PRM MATOLA (police_station_id=2)
            // =============================================
            [
                'police_station_id' => 2,
                'area_estudo_id' => 1, // Matola A
                'name' => '1ª Esquadra PRM Matola - Matola A',
                'description' => null,
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            // Matola B removida da 1ª Esquadra — transferida para 2ª Esquadra (zona Cinema 700)
            [
                'police_station_id' => 2,
                'area_estudo_id' => 6, // Matola C
                'name' => '1ª Esquadra PRM Matola - Matola C',
                'description' => null,
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'police_station_id' => 2,
                'area_estudo_id' => 7, // Matola D - PARCIAL
                'name' => '1ª Esquadra PRM Matola - Matola D (parcial)',
                'description' => 'Jurisdição parcial sobre a Matola D. Avenida das Indústrias como referência da linha divisória. Divisão geométrica exata pendente de confirmação oficial.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial. Divisão parcial não confirmada oficialmente.',
                'is_active' => true,
            ],

            // =============================================
            // 2ª ESQUADRA PRM MATOLA (police_station_id=10)
            // =============================================
            [
                'police_station_id' => 10,
                'area_estudo_id' => 7, // Matola D - PARCIAL
                'name' => '2ª Esquadra PRM Matola - Matola D (parcial)',
                'description' => 'Jurisdição parcial sobre a Matola D. Avenida das Indústrias como referência da linha divisória. Divisão geométrica exata pendente de confirmação oficial.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial. Divisão parcial não confirmada oficialmente.',
                'is_active' => true,
            ],
            [
                'police_station_id' => 10,
                'area_estudo_id' => 5, // Matola B
                'name' => '2ª Esquadra PRM Matola - Matola B',
                'description' => 'Zona Cinema 700 — sede da 2ª Esquadra.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial. Corrigido na Fase 3B Etapa 5.',
                'is_active' => true,
            ],
            [
                'police_station_id' => 10,
                'area_estudo_id' => 8, // Matola F
                'name' => '2ª Esquadra PRM Matola - Matola F',
                'description' => null,
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'police_station_id' => 10,
                'area_estudo_id' => 9, // Matola G
                'name' => '2ª Esquadra PRM Matola - Matola G',
                'description' => null,
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'police_station_id' => 10,
                'area_estudo_id' => 10, // Matola H
                'name' => '2ª Esquadra PRM Matola - Matola H',
                'description' => null,
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            // Matola J removida da 2ª Esquadra — transferida para 4ª Esquadra da Liberdade

            // =============================================
            // 3ª ESQUADRA PRM MATOLA (police_station_id=11)
            // =============================================
            [
                'police_station_id' => 11,
                'area_estudo_id' => 2, // Fomento
                'name' => '3ª Esquadra PRM Matola - Fomento',
                'description' => null,
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],

            // =============================================
            // 4ª ESQUADRA DA LIBERDADE (police_station_id=7)
            // =============================================
            [
                'police_station_id' => 7,
                'area_estudo_id' => 13, // Cikwama
                'name' => '4ª Esquadra da Liberdade - Cikwama',
                'description' => 'Nota: Na tabela "unidades policiais" aparece como "Sikwama". Na tabela "Area de Estudo" aparece como "Cikwama".',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'police_station_id' => 7,
                'area_estudo_id' => 3, // Liberdade
                'name' => '4ª Esquadra da Liberdade - Liberdade',
                'description' => null,
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'police_station_id' => 7,
                'area_estudo_id' => 4, // Malhampsene
                'name' => '4ª Esquadra da Liberdade - Malhampsene',
                'description' => null,
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'police_station_id' => 7,
                'area_estudo_id' => 12, // Mussumbuluco
                'name' => '4ª Esquadra da Liberdade - Mussumbuluco',
                'description' => null,
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'police_station_id' => 7,
                'area_estudo_id' => 11, // Matola J
                'name' => '4ª Esquadra da Liberdade - Matola J',
                'description' => 'Transferida da 2ª Esquadra para a 4ª Esquadra da Liberdade conforme validação documental.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial. Corrigido na Fase 3B Etapa 5.',
                'is_active' => true,
            ],
        ];

        foreach ($jurisdictions as $j) {
            Jurisdiction::firstOrCreate(
                [
                    'police_station_id' => $j['police_station_id'],
                    'area_estudo_id' => $j['area_estudo_id'],
                ],
                $j
            );
        }
    }
}
