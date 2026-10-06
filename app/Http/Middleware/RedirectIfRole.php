<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfRole
{
    /**
     * Handle an incoming request.
     *
     * Redirects users to their appropriate dashboard based on role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->hasRole('admin') && !$request->routeIs('admin.*')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole('district_commander') && !$request->routeIs('district_commander.*')) {
            return redirect()->route('district_commander.dashboard');
        }

        if ($user->hasRole('squad_commander') && !$request->routeIs('squad_commander.*')) {
            return redirect()->route('squad_commander.dashboard');
        }

        if ($user->hasRole('post_commander') && !$request->routeIs('post_commander.*')) {
            return redirect()->route('post_commander.dashboard');
        }

        if ($user->hasRole('manager') && !$request->routeIs('manager.*')) {
            return redirect()->route('manager.dashboard');
        }

        if ($user->hasRole('police') && !$request->routeIs('police.*')) {
            return redirect()->route('police.dashboard');
        }

        if ($user->hasRole('sernic_officer') && !$request->routeIs('sernic_officer.*')) {
            return redirect()->route('sernic_officer.dashboard');
        }

        if ($user->hasRole('citizen') && !$request->routeIs('citizen.*')) {
            return redirect()->route('citizen.dashboard');
        }

        return $next($request);
    }
}
