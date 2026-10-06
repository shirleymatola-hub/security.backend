<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;

// URL final: /api/auth/...
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:smp-login')->name('login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:smp-register')->name('register');

    // Recuperação de palavra-passe: pedir código → confirmar código → nova palavra-passe
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:smp-password')->name('forgot-password');
    Route::post('/confirm-code', [AuthController::class, 'confirmCode'])->middleware('throttle:smp-password')->name('confirm-code');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:smp-password-update')->name('reset-password');

    // Google OAuth (apenas cidadãos). O browser navega para /redirect;
    // GOOGLE_REDIRECT_URI deve apontar para /api/auth/google/callback.
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:smp-google')->name('google.redirect');
    Route::get('/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:smp-google')->name('google.callback');
    Route::get('/google/link/{key}', [GoogleAuthController::class, 'linkInfo'])->middleware('throttle:smp-google')->name('google.link.info');
    Route::post('/google/link', [GoogleAuthController::class, 'processLink'])->middleware('throttle:smp-google')->name('google.link.process');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});
