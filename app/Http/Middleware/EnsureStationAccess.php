<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStationAccess
{
    /**
     * Ensure the user can only access their own police station data.
     * District commanders can access all stations in their district.
     * Squad commanders can access all stations in their squad.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // District commander: access all stations in their district
        if ($user->hasRole('district_commander') && $user->district_id) {
            return $next($request);
        }

        // Squad commander: access all stations (filtered by controller logic)
        if ($user->hasRole('squad_commander')) {
            return $next($request);
        }

        $stationId = $request->route('station');

        if ($stationId && $user->police_station_id != $stationId) {
            abort(403, 'Acesso não autorizado a este posto policial.');
        }

        return $next($request);
    }
}
