<?php

namespace App\Http\Middleware;

use App\Models\SuperAdminUser;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usado depois de auth:super_admin. Equivalente ao antigo middleware
 * SuperAdminAuth: contas suspensas ou bloqueadas perdem o token e são
 * enviadas de volta para o login do painel.
 */
class EnsureSuperAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('super_admin');

        if (! $user instanceof SuperAdminUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $message = null;
        if ($user->isSuspended()) {
            $message = 'A sua conta está suspensa. Contacte o administrador do sistema.';
        } elseif ($user->isLocked()) {
            $message = 'A sua conta está temporariamente bloqueada. Tente novamente mais tarde.';
        }

        if ($message) {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json(['message' => $message], 401);
        }

        return $next($request);
    }
}
