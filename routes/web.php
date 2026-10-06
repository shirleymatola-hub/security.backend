<?php

use Illuminate\Support\Facades\Route;

/*
| Este projeto é apenas a API (ver routes/api.php).
| As páginas estão no frontend React (repositório SMP_frontend).
*/
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'ok',
    'api' => url('/api'),
    'frontend' => config('app.frontend_url'),
]));
