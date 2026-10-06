<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicSite\AnonymousReportController;
use App\Http\Controllers\PublicSite\IncidentController;

// Rotas públicas (sem autenticação) — URL final: /api/public/...
// Conversão das closures públicas do routes/web.php original.
// A aceitação de convites usa a API interna já existente (routes/internal_api.php):
//   GET /api/internal/invite/{token} e POST /api/internal/invite/accept
Route::prefix('public')->name('public.')->group(function () {
    // GET /ocorrencias → lista paginada de ocorrências públicas + categorias ativas
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents');

    // GET /denuncia-anonima → bairros para o formulário
    Route::get('/neighborhoods', [AnonymousReportController::class, 'neighborhoods'])->name('neighborhoods');

    // POST /denuncia-anonima → criar denúncia anónima (multipart: anexos e áudio)
    Route::post('/anonymous-reports', [AnonymousReportController::class, 'store'])
        ->middleware('throttle:smp-anonymous')
        ->name('anonymous-reports.store');
});
