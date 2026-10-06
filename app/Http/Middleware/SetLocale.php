<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    private const SUPPORTED = ['pt', 'en'];

    /**
     * O frontend envia o idioma escolhido no cabeçalho X-Locale.
     * Sem cabeçalho, usa o idioma guardado no perfil do utilizador.
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('X-Locale')
            ?: $request->user()?->locale
            ?: config('app.locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
