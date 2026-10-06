<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        if (Category::count() > 0) {
            return;
        }

        $categories = [
            [
                'name' => 'Roubo',
                'slug' => 'roubo',
                'description' => 'Roubo com ou sem violência',
                'icon' => 'lock',
                'color' => '#ba1a1a',
                'is_active' => true,
            ],
            [
                'name' => 'Furto',
                'slug' => 'furto',
                'description' => 'Apropriação indevida de bens',
                'icon' => 'handshake',
                'color' => '#f97316',
                'is_active' => true,
            ],
            [
                'name' => 'Violência Doméstica',
                'slug' => 'violencia-domestica',
                'description' => 'Violência no âmbito doméstico e familiar',
                'icon' => 'home',
                'color' => '#db2777',
                'is_active' => true,
            ],
            [
                'name' => 'Acidente de Viação',
                'slug' => 'acidente-de-viacao',
                'description' => 'Acidentes de trânsito e viação',
                'icon' => 'traffic',
                'color' => '#0891b2',
                'is_active' => true,
            ],
            [
                'name' => 'Incêndio',
                'slug' => 'incendio',
                'description' => 'Incêndios e sinistros de fogo',
                'icon' => 'local_fire_department',
                'color' => '#ea580c',
                'is_active' => true,
            ],
            [
                'name' => 'Vandalismo',
                'slug' => 'vandalismo',
                'description' => 'Destruição ou dano a propriedade',
                'icon' => 'broken_image',
                'color' => '#7c3aed',
                'is_active' => true,
            ],
            [
                'name' => 'Perturbação da Ordem Pública',
                'slug' => 'perturbacao-ordem-publica',
                'description' => 'Perturbação da tranquilidade e ordem pública',
                'icon' => 'campaign',
                'color' => '#d97706',
                'is_active' => true,
            ],
            [
                'name' => 'Pessoa Desaparecida',
                'slug' => 'pessoa-desaparecida',
                'description' => 'Reporte de pessoas desaparecidas',
                'icon' => 'person_search',
                'color' => '#6366f1',
                'is_active' => true,
            ],
            [
                'name' => 'Desastre Natural',
                'slug' => 'desastre-natural',
                'description' => 'Desastres naturais e catástrofes ambientais',
                'icon' => 'thunderstorm',
                'color' => '#0284c7',
                'is_active' => true,
            ],
            [
                'name' => 'Fraude',
                'slug' => 'fraude',
                'description' => 'Actos fraudulentos e engano doloso',
                'icon' => 'credit_card_off',
                'color' => '#9333ea',
                'is_active' => true,
            ],
            [
                'name' => 'Agressão Física',
                'slug' => 'agressao-fisica',
                'description' => 'Agressão física a pessoas',
                'icon' => 'gpp_bad',
                'color' => '#dc2626',
                'is_active' => true,
            ],
            [
                'name' => 'Homicídio',
                'slug' => 'homicidio',
                'description' => 'Homicídio e tentativa de homicídio',
                'icon' => 'dangerous',
                'color' => '#881337',
                'is_active' => true,
            ],
            [
                'name' => 'Tráfico de Drogas',
                'slug' => 'trafico-de-drogas',
                'description' => 'Tráfico e porte de estupefacientes',
                'icon' => 'medication',
                'color' => '#4f46e5',
                'is_active' => true,
            ],
            [
                'name' => 'Violação',
                'slug' => 'violacao',
                'description' => 'Crimes sexuais e violação',
                'icon' => 'no_accounts',
                'color' => '#be123c',
                'is_active' => true,
            ],
            [
                'name' => 'Outro',
                'slug' => 'outro',
                'description' => 'Outras ocorrências não categorizadas',
                'icon' => 'more_horiz',
                'color' => '#737780',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
