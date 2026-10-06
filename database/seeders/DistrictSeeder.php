<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\District;
use App\Models\Neighborhood;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        // Criar Distrito Matola C
        $district = District::create([
            'name' => 'Matola C',
            'province' => 'Maputo',
            'latitude' => -25.9692,
            'longitude' => 32.4539,
        ]);

        // Criar Bairros do Matola C
        $neighborhoods = [
            ['name' => 'Liberdade', 'latitude' => -25.9650, 'longitude' => 32.4500],
            ['name' => 'Ndlavela', 'latitude' => -25.9700, 'longitude' => 32.4580],
            ['name' => 'T3', 'latitude' => -25.9750, 'longitude' => 32.4490],
            ['name' => 'Matola Gare', 'latitude' => -25.9600, 'longitude' => 32.4450],
            ['name' => 'Fomento', 'latitude' => -25.9680, 'longitude' => 32.4550],
            ['name' => 'Machava', 'latitude' => -25.9720, 'longitude' => 32.4420],
            ['name' => 'Zona Industrial', 'latitude' => -25.9580, 'longitude' => 32.4600],
        ];

        foreach ($neighborhoods as $neighborhood) {
            $district->neighborhoods()->create($neighborhood);
        }
    }
}
