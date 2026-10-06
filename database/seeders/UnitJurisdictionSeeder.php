<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UnitJurisdiction;

class UnitJurisdictionSeeder extends Seeder
{
    public function run(): void
    {
        $effectiveDate = '2026-09-07';

        $unitJurisdictions = [
            // =============================================
            // 1ª ESQUADRA PRM MATOLA (police_station_id=2)
            // =============================================
            [
                'unidade_policial_id' => 1,
                'area_estudo_id' => 1, // Matola A
                'name' => 'Área de atuação — Esquadra da Cidade da Matola',
                'description' => 'Área de atuação da Esquadra da Cidade da Matola no bairro Matola A, dentro da Área de Estudo.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],

            // =============================================
            // 2ª ESQUADRA PRM MATOLA (police_station_id=10)
            // =============================================
            [
                'unidade_policial_id' => 2,
                'area_estudo_id' => 9, // Matola G
                'name' => 'Área de atuação — Posto Policial de Matola G',
                'description' => 'Área de atuação da unidade policial no bairro Matola G, dentro da Área de Estudo.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'unidade_policial_id' => 3,
                'area_estudo_id' => 10, // Matola H
                'name' => 'Área de atuação — Posto Policial de Matola H',
                'description' => 'Área de atuação da unidade policial no bairro Matola H, dentro da Área de Estudo.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'unidade_policial_id' => 4,
                'area_estudo_id' => 5, // Matola B
                'name' => 'Área de atuação — 2ª Esquadra PRM Matola (Matola B / Cinema 700)',
                'description' => 'Área de atuação da 2ª Esquadra PRM Matola (infraestrutura Cinema 700) no bairro Matola B, dentro da Área de Estudo.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial. Corrigido na Fase 3B Etapa 5.',
                'is_active' => true,
            ],
            [
                'unidade_policial_id' => 6,
                'area_estudo_id' => 6, // Matola C
                'name' => 'Área de atuação — Posto Policial de Matola C',
                'description' => 'Área de atuação da unidade policial no bairro Matola C, dentro da Área de Estudo.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],

            // =============================================
            // 3ª ESQUADRA PRM MATOLA (police_station_id=11)
            // =============================================
            [
                'unidade_policial_id' => 7,
                'area_estudo_id' => 2, // Fomento
                'name' => 'Área de atuação — Esquadra do Fomento',
                'description' => 'Área de atuação da Esquadra do Fomento no bairro Fomento, dentro da Área de Estudo.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],

            // =============================================
            // 4ª ESQUADRA DA LIBERDADE (police_station_id=7)
            // =============================================
            [
                'unidade_policial_id' => 10,
                'area_estudo_id' => 3, // Liberdade
                'name' => 'Área de atuação — Esquadra da Liberdade',
                'description' => 'Área de atuação da Esquadra da Liberdade no bairro Liberdade, dentro da Área de Estudo.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'unidade_policial_id' => 11,
                'area_estudo_id' => 12, // Mussumbuluco
                'name' => 'Área de atuação — Posto de Mussumbuluco',
                'description' => 'Área de atuação da unidade policial no bairro Mussumbuluco, dentro da Área de Estudo.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
            [
                'unidade_policial_id' => 12,
                'area_estudo_id' => 13, // Cikwama
                'name' => 'Área de atuação — PPC de Sikwama',
                'description' => 'Área de atuação da unidade policial no bairro Cikwama (registrado como Sikwama na tabela de unidades policiais), dentro da Área de Estudo.',
                'effective_date' => $effectiveDate,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
                'is_active' => true,
            ],
        ];

        foreach ($unitJurisdictions as $uj) {
            UnitJurisdiction::firstOrCreate(
                [
                    'unidade_policial_id' => $uj['unidade_policial_id'],
                    'area_estudo_id' => $uj['area_estudo_id'],
                ],
                $uj
            );
        }
    }
}
