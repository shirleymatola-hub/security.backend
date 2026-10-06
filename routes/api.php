<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API do SMP
|--------------------------------------------------------------------------
|
| Todas as rotas têm o prefixo /api (definido em bootstrap/app.php).
| O frontend React (SMP_frontend) consome estas rotas enviando o token
| Sanctum no cabeçalho "Authorization: Bearer <token>".
|
| Cada módulo tem o seu ficheiro em routes/api/.
|
*/

// Autenticação (login, registo, recuperação de palavra-passe, Google)
require __DIR__ . '/api/auth.php';

// Páginas públicas (denúncia anónima, ocorrências públicas, convites)
require __DIR__ . '/api/public.php';

// Dados do mapa (camadas, estações, rotas). Algumas rotas exigem token.
require __DIR__ . '/api/map.php';

// Rotas que exigem utilizador autenticado
Route::middleware('auth:sanctum')->group(function () {
    // Comuns a todos os perfis: perfil, notificações, lembretes, tema, idioma
    require __DIR__ . '/api/common.php';

    // Módulos por perfil (cada ficheiro aplica o middleware role:...)
    require __DIR__ . '/api/admin.php';
    require __DIR__ . '/api/district_commander.php';
    require __DIR__ . '/api/squad_commander.php';
    require __DIR__ . '/api/post_commander.php';
    require __DIR__ . '/api/manager.php';
    require __DIR__ . '/api/police.php';
    require __DIR__ . '/api/sernic.php';
    require __DIR__ . '/api/citizen.php';
});
