<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PoliceStation;
use App\Models\User;
use App\Models\District;
use Illuminate\Support\Facades\Hash;

class PoliceStationSeeder extends Seeder
{
    public function run(): void
    {
        $district = District::firstOrCreate(
            ['name' => 'Matola C'],
            [
                'province' => 'Maputo',
                'latitude' => -25.9692,
                'longitude' => 32.4539,
            ]
        );

        // Criar Gestor do Posto
        $manager = User::firstOrCreate(
            ['email' => 'gestor@matolac.mz'],
            [
                'name' => 'Carlos Mondlane',
                'phone' => '+258 84 123 4567',
                'password' => Hash::make('password'),
                'police_station_id' => null,
                'status' => 'active',
            ]
        );
        $manager->assignRole('manager');

        // Criar Posto Policial Principal
        // Coordenadas reais: 3ª Esquadra - PRM (OSM node 12128525998)
        $station = PoliceStation::firstOrCreate(
            ['code' => 'MC-001'],
            [
                'name' => 'Posto Policial Matola C - Sede',
                'address' => 'Rua das Acácias, nº 124, Matola C',
                'phone' => '+258 21 000 000',
                'email' => 'sede@matolac.mz',
                'latitude' => -25.9263421,
                'longitude' => 32.4818772,
                'district_id' => $district->id,
                'commander_id' => $manager->id,
                'is_active' => true,
            ]
        );

        // Atualizar o gestor com o posto
        $manager->update(['police_station_id' => $station->id]);

        // Criar Policial
        $police = User::firstOrCreate(
            ['email' => 'agente@matolac.mz'],
            [
                'name' => 'Miguel Tembe',
                'phone' => '+258 84 987 6543',
                'password' => Hash::make('password'),
                'police_station_id' => $station->id,
                'status' => 'active',
            ]
        );
        $police->assignRole('police');

        // Criar Cidadão
        $citizen = User::firstOrCreate(
            ['email' => 'cidadao@email.mz'],
            [
                'name' => 'Maria Silva',
                'phone' => '+258 84 555 1234',
                'password' => Hash::make('password'),
                'police_station_id' => null,
                'status' => 'active',
            ]
        );
        $citizen->assignRole('citizen');
    }
}
