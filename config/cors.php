<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | O frontend React corre noutro domínio (ex.: smp-frontend.onrender.com),
    | por isso só esse(s) endereço(s) podem chamar a API. Vários endereços
    | podem ser indicados em CORS_ALLOWED_ORIGINS separados por vírgula
    | (por omissão usa FRONTEND_URL).
    |
    | A autenticação é por token (Authorization: Bearer), sem cookies.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        fn ($url) => rtrim(trim($url), '/'),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:5173')))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Necessário para o frontend ler o nome dos ficheiros PDF/CSV descarregados.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
