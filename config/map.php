<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenStreetMap / Leaflet Configuration
    |--------------------------------------------------------------------------
    |
    | Configuração para mapas usando OpenStreetMap com Leaflet.js
    |
    */

    // Tile URL do OpenStreetMap
    'tile_url' => env('OSM_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',

    // Centro padrão do mapa (Bairro Matola C, Moçambique)
    'default_center' => [
        'lat' => (float) env('OSM_DEFAULT_LAT', -25.9692),
        'lng' => (float) env('OSM_DEFAULT_LNG', 32.4539),
        'zoom' => (int) env('OSM_DEFAULT_ZOOM', 13),
    ],

    // Limites do mapa (Município da Matola)
    'bounds' => [
        'south_west' => ['lat' => -26.10, 'lng' => 32.20],
        'north_east' => ['lat' => -25.70, 'lng' => 32.80],
    ],

    // Área de estudo — limites do Município da Matola (para filtrar postos fora da área)
    'study_area' => [
        'south_west' => ['lat' => -26.05, 'lng' => 32.35],
        'north_east' => ['lat' => -25.82, 'lng' => 32.56],
    ],
    // Cores dos marcadores por prioridade
    'marker_colors' => [
        'low' => '#22c55e',
        'medium' => '#f59e0b',
        'high' => '#f97316',
        'urgent' => '#ba1a1a',
    ],

    // Ícones por categoria (Material Symbols)
    'default_icon' => 'warning',
];