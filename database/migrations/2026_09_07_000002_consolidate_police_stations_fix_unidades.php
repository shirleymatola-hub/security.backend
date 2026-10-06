<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // =========================================================
        // 1. Corrigir police_station_id na tabela "unidades policiais"
        //    Idempotente: só atualiza se o valor ainda estiver errado.
        // =========================================================

        // Unidade 2 (Posto Policial de Matola G): deve ser ps_id=10
        DB::statement('UPDATE "unidades policiais" SET police_station_id = 10 WHERE id = 2 AND police_station_id != 10');

        // Unidade 3 (Posto Policial de Matola H): deve ser ps_id=10
        DB::statement('UPDATE "unidades policiais" SET police_station_id = 10 WHERE id = 3 AND police_station_id != 10');

        // Unidade 6 (Posto Policial de Matola C): deve ser ps_id=2
        DB::statement('UPDATE "unidades policiais" SET police_station_id = 2 WHERE id = 6 AND police_station_id != 2');

        // Unidade 4 (Esquadra da Matola 700): deve ser ps_id=10
        DB::statement('UPDATE "unidades policiais" SET police_station_id = 10 WHERE id = 4 AND police_station_id != 10');

        // =========================================================
        // 2. Migrar referências do police_stations.id=4 para id=10
        //    Idempotente: só atualiza se o ID 4 ainda existir.
        // =========================================================

        $station4Exists = DB::table('police_stations')->where('id', 4)->exists();

        if ($station4Exists) {
            DB::table('incidents')
                ->where('police_station_id', 4)
                ->update(['police_station_id' => 10]);

            DB::table('users')
                ->where('police_station_id', 4)
                ->update(['police_station_id' => 10]);

            DB::table('neighborhood_police_station')
                ->where('police_station_id', 4)
                ->update(['police_station_id' => 10]);

            DB::table('internal_user_invitations')
                ->where('police_station_id', 4)
                ->update(['police_station_id' => 10]);

            DB::table('police_stations')
                ->where('id', 4)
                ->delete();
        }

        // =========================================================
        // 3. Atualizar nome do registro final da 2ª Esquadra
        //    Idempotente: só atualiza se o nome ainda estiver diferente.
        // =========================================================

        DB::table('police_stations')
            ->where('id', 10)
            ->where('name', '!=', '2ª Esquadra PRM Matola - Matola B / Cinema 700')
            ->update([
                'name' => '2ª Esquadra PRM Matola - Matola B / Cinema 700',
            ]);

        // =========================================================
        // 4. Corrigir jurisdictions
        //    Matola B (area=5) → 2ª Esquadra (ps_id=10)
        //    Matola J (area=11) → 4ª Esquadra (ps_id=7)
        // =========================================================

        // Matola B: mover de 1ª para 2ª Esquadra
        DB::table('jurisdictions')
            ->where('area_estudo_id', 5)
            ->where('police_station_id', 2)
            ->update([
                'police_station_id' => 10,
                'name' => '2ª Esquadra PRM Matola - Matola B',
                'description' => 'Zona Cinema 700 — sede da 2ª Esquadra.',
                'source_document' => 'Informação de trabalho - entrevista com agente policial. Corrigido na Fase 3B Etapa 5.',
            ]);

        // Matola J: mover de 2ª para 4ª Esquadra
        DB::table('jurisdictions')
            ->where('area_estudo_id', 11)
            ->where('police_station_id', 10)
            ->update([
                'police_station_id' => 7,
                'name' => '4ª Esquadra da Liberdade - Matola J',
                'description' => 'Transferida da 2ª Esquadra para a 4ª Esquadra da Liberdade conforme validação documental.',
                'source_document' => 'Informação de trabalho - entrevista com agente policial. Corrigido na Fase 3B Etapa 5.',
            ]);

        // Remover duplicatas de Matola J (se existirem)
        $matolaJRecords = DB::table('jurisdictions')
            ->where('area_estudo_id', 11)
            ->where('is_active', true)
            ->get();

        if ($matolaJRecords->count() > 1) {
            // Manter apenas o registro da 4ª Esquadra
            DB::table('jurisdictions')
                ->where('area_estudo_id', 11)
                ->where('police_station_id', 10)
                ->where('is_active', true)
                ->delete();
        }
    }

    public function down(): void
    {
        // Reverter nome da 2ª Esquadra
        DB::table('police_stations')
            ->where('id', 10)
            ->where('name', 'like', '%Cinema 700%')
            ->update([
                'name' => '2a Esquadra PRM Matola',
            ]);

        // Reverter Matola B: voltar para 1ª Esquadra
        DB::table('jurisdictions')
            ->where('area_estudo_id', 5)
            ->where('police_station_id', 10)
            ->update([
                'police_station_id' => 2,
                'name' => '1ª Esquadra PRM Matola - Matola B',
                'description' => null,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
            ]);

        // Reverter Matola J: voltar para 2ª Esquadra
        DB::table('jurisdictions')
            ->where('area_estudo_id', 11)
            ->where('police_station_id', 7)
            ->update([
                'police_station_id' => 10,
                'name' => '2ª Esquadra PRM Matola - Matola J',
                'description' => null,
                'source_document' => 'Informação de trabalho - entrevista com agente policial',
            ]);

        // Recriar police_stations.id=4
        $station4Exists = DB::table('police_stations')->where('id', 4)->exists();
        if (!$station4Exists) {
            DB::table('police_stations')->insert([
                'id' => 4,
                'name' => 'Esquadra d matola 700',
                'code' => 'M700-001',
                'address' => 'Av. 5 de Fevereiro, Matola-Sede',
                'latitude' => -25.94175,
                'longitude' => 32.455083,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Reverter unidades policiais
        DB::statement('UPDATE "unidades policiais" SET police_station_id = 2 WHERE id = 2');
        DB::statement('UPDATE "unidades policiais" SET police_station_id = 2 WHERE id = 3');
        DB::statement('UPDATE "unidades policiais" SET police_station_id = 10 WHERE id = 6');
        DB::statement('UPDATE "unidades policiais" SET police_station_id = 4 WHERE id = 4');
    }
};
