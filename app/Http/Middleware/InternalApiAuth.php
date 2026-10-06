<?php

namespace App\Http\Middleware;

use App\Models\InternalApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalApiAuth
{
    public function handle(Request $request, Closure $next, ?string $requiredAbility = null): Response
    {
        if (!config('internal_api.enabled', false)) {
            return response()->json(['success' => false, 'message' => 'Internal API is disabled.'], 503);
        }

        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $hash = hash('sha256', $token);

        $apiToken = InternalApiToken::where('token_hash', $hash)->first();

        if (!$apiToken) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        if ($apiToken->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Token has expired.'], 401);
        }

        $allowedIps = config('internal_api.allowed_ips', []);
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if ($requiredAbility && !$apiToken->hasAbility($requiredAbility)) {
            return response()->json(['success' => false, 'message' => 'Insufficient permissions.'], 403);
        }

        $apiToken->recordUsage();

        $request->attributes->set('internal_api_token', $apiToken);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if ($bearer) {
            return $bearer;
        }

        $header = $request->header('X-Internal-Api-Token');

        if ($header) {
            return $header;
        }

        return null;
    }
}
