<?php

namespace App\Http\Middleware;

use App\Models\PoliceStation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStationAccess
{
    /**
     * Limita o acesso a dados por esquadra.
     * Admin e SERNIC acedem a todas; comandante distrital apenas ao seu distrito;
     * os restantes perfis apenas à sua própria esquadra.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            abort(401, 'Autenticação necessária.');
        }

        $stationId = $request->route('stationId') ?? $request->route('station');

        if ($stationId === null) {
            return $next($request);
        }

        $stationId = (int) $stationId;

        if ($user->hasRole('admin') || $user->hasRole('sernic_officer')) {
            return $next($request);
        }

        if ($user->hasRole('district_commander') && $user->district_id) {
            $allowed = PoliceStation::where('id', $stationId)
                ->where('district_id', $user->district_id)
                ->exists();

            if ($allowed) {
                return $next($request);
            }
        }

        if ($user->police_station_id !== null && (int) $user->police_station_id === $stationId) {
            return $next($request);
        }

        abort(403, 'Acesso não autorizado a este posto policial.');
    }
}
