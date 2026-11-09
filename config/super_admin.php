<?php

/*
|--------------------------------------------------------------------------
| Painel Super Admin
|--------------------------------------------------------------------------
|
| Rotas em routes/api/super_admin.php (/api/super-admin/*) e páginas em
| SMP_frontend/src/pages/super-admin. As contas ficam na tabela
| super_admin_users (criar com: php artisan super-admin:create).
|
*/

return [

    // Duração do token de sessão (horas). Com "Lembrar-me" usa remember_days.
    'token_hours' => (int) env('SUPER_ADMIN_TOKEN_HOURS', 12),
    'remember_days' => (int) env('SUPER_ADMIN_REMEMBER_DAYS', 30),

    // Tempo para introduzir o código 2FA depois da palavra-passe (minutos).
    'two_factor_challenge_minutes' => 10,

    // Validade do link de recuperação de palavra-passe (minutos).
    'password_reset_minutes' => 30,

    // Validade dos convites para utilizadores operacionais (horas).
    'invitation_expires_hours' => (int) env('INVITATION_EXPIRES_HOURS', 72),

    // Conta criada por "php artisan super-admin:create" (e no arranque do contentor).
    'account' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Administrator'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

];
