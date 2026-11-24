<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

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
            'station.access' => \App\Http\Middleware\EnsureStationAccess::class,
            'check_lockout' => \App\Http\Middleware\CheckAccountLockout::class,
            'super_admin.active' => \App\Http\Middleware\EnsureSuperAdminIsActive::class,
        ]);
        $middleware->api(prepend: [
            \App\Http\Middleware\UseSanctumGuard::class,
            \App\Http\Middleware\SetLocale::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\CheckAccountLockout::class,
        ]);
        // Pedidos sem token para rotas protegidas: o frontend trata do ecrã de login.
        $middleware->redirectGuestsTo(fn () => frontend_url('/login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Esta aplicação é só API: todos os erros são devolvidos em JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if (in_array($status, [401, 429], true)) {
                $data = json_decode((string) $response->getContent(), true);

                if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
                    $data['message'] = __($data['message']);
                    $headers = $response->headers->has('Retry-After')
                        ? ['Retry-After' => $response->headers->get('Retry-After')]
                        : [];

                    return response()->json($data, $status, $headers);
                }
            }

            if ($status !== 500) {
                return $response;
            }

            return response()->json([
                'message' => __('Não foi possível concluir a operação. Tente novamente.'),
            ], 500);
        });
    })->create();
