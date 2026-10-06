<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/internal_api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'set-locale' => \App\Http\Middleware\SetLocale::class,
            'internal_api' => \App\Http\Middleware\InternalApiAuth::class,
            'check_lockout' => \App\Http\Middleware\CheckAccountLockout::class,
        ]);
        $middleware->api(prepend: [
            \App\Http\Middleware\UseSanctumGuard::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\CheckAccountLockout::class,
        ]);
        // Pedidos sem token para rotas protegidas: o frontend trata do ecrã de login.
        $middleware->redirectGuestsTo(fn () => frontend_url('/login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Esta aplicação é só API: todos os erros são devolvidos em JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
