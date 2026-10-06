<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ThemeController;

// Incluído dentro do grupo auth:sanctum → comum a todos os perfis.

// Perfil do utilizador autenticado (para enviar foto: POST com _method=PUT)
Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');

Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');

Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
