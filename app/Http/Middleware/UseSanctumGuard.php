<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseSanctumGuard
{
    /**
     * Faz com que auth()->user() / auth()->id() resolvam o utilizador a partir
     * do token Bearer em todas as rotas da API — incluindo as públicas, onde o
     * token é opcional (ex.: mapa público com dados extra para quem tem sessão).
     */
    public function handle(Request $request, Closure $next): Response
    {
        auth()->shouldUse('sanctum');

        return $next($request);
    }
}
