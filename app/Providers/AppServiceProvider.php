<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
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
    }
}
