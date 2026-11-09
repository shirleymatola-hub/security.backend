<?php

use App\Http\Controllers\SuperAdmin\AuditController;
use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\SuperAdmin\CentralMapController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\DistrictController;
use App\Http\Controllers\SuperAdmin\ExportController;
use App\Http\Controllers\SuperAdmin\GlobalSearchController;
use App\Http\Controllers\SuperAdmin\InvitationController;
use App\Http\Controllers\SuperAdmin\NeighborhoodController;
use App\Http\Controllers\SuperAdmin\OperationalUserController;
use App\Http\Controllers\SuperAdmin\ProfileController;
use App\Http\Controllers\SuperAdmin\ReportController;
use App\Http\Controllers\SuperAdmin\SettingsController;
use App\Http\Controllers\SuperAdmin\StationController;
use App\Http\Controllers\SuperAdmin\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Painel Super Admin — URL final: /api/super-admin/...
|--------------------------------------------------------------------------
|
| Antes era um projeto Laravel separado (pasta super-admin, views Blade,
| base de dados própria). Agora faz parte desta API e usa a mesma base de
| dados. As páginas estão no frontend React em /super-admin/*.
|
| Autenticação: token Sanctum de SuperAdminUser no guard "super_admin"
| (os tokens dos utilizadores do SMP não são aceites aqui, e vice-versa).
|
*/

Route::prefix('super-admin')->name('super-admin.')->group(function () {

    // Sem sessão
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:super-admin-login')->name('login');
        Route::post('/two-factor', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:super-admin-2fa')->name('two-factor');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:super-admin-password')->name('forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:super-admin-password-reset')->name('reset-password');
    });

    Route::middleware(['auth:super_admin', 'super_admin.active'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Perfil e segurança
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:super-admin-password-change')->name('profile.password');

        Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::middleware('throttle:super-admin-2fa-ops')->group(function () {
            Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
            Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
            Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'regenerate'])->name('two-factor.recovery-codes');
        });

        // Convites para utilizadores operacionais
        Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::get('/invitations/options', [InvitationController::class, 'options'])->name('invitations.options');
        Route::post('/invitations', [InvitationController::class, 'store'])->middleware('throttle:super-admin-invitations')->name('invitations.store');
        Route::get('/invitations/{id}', [InvitationController::class, 'show'])->whereNumber('id')->name('invitations.show');
        Route::post('/invitations/{id}/cancel', [InvitationController::class, 'cancel'])->whereNumber('id')->middleware('throttle:super-admin-invitations')->name('invitations.cancel');

        // Gestão operacional (só leitura)
        Route::get('/stations', [StationController::class, 'index'])->name('stations.index');
        Route::get('/stations/{id}', [StationController::class, 'show'])->whereNumber('id')->name('stations.show');
        Route::get('/districts', [DistrictController::class, 'index'])->name('districts.index');
        Route::get('/districts/{id}', [DistrictController::class, 'show'])->whereNumber('id')->name('districts.show');
        Route::get('/neighborhoods', [NeighborhoodController::class, 'index'])->name('neighborhoods.index');
        Route::get('/neighborhoods/{id}', [NeighborhoodController::class, 'show'])->whereNumber('id')->name('neighborhoods.show');
        Route::get('/operational-users', [OperationalUserController::class, 'index'])->name('operational-users.index');
        Route::get('/operational-users/{id}', [OperationalUserController::class, 'show'])->whereNumber('id')->name('operational-users.show');

        Route::get('/map', [CentralMapController::class, 'index'])->name('map');
        Route::get('/search', [GlobalSearchController::class, 'search'])->name('search');

        // Exportações (CSV, Excel, PDF) e relatório administrativo
        Route::get('/export/{type}/{format}', [ExportController::class, 'export'])
            ->whereIn('type', ['stations', 'districts', 'neighborhoods', 'users', 'citizens', 'incidents'])
            ->whereIn('format', ['csv', 'excel', 'pdf'])
            ->name('export');
        Route::get('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');

        // Auditoria do painel
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/{id}', [AuditController::class, 'show'])->whereNumber('id')->name('audit.show');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    });
});
