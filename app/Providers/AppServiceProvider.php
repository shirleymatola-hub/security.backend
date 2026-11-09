<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('smp-login', fn () => Limit::perMinute(5));
        RateLimiter::for('smp-register', fn () => Limit::perMinute(3));
        RateLimiter::for('smp-password', fn () => Limit::perMinute(3));
        RateLimiter::for('smp-password-update', fn () => Limit::perMinute(3));
        RateLimiter::for('smp-google', fn () => Limit::perMinute(10));
        RateLimiter::for('smp-anonymous', fn () => Limit::perMinute(5));

        // Painel Super Admin (routes/api/super_admin.php)
        RateLimiter::for('super-admin-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('super-admin-2fa', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('super-admin-2fa-ops', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('super-admin-password', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('super-admin-password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('super-admin-password-change', fn (Request $request) => Limit::perMinute(5)->by($request->user('super_admin')?->id ?: $request->ip()));
        RateLimiter::for('super-admin-invitations', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
