<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Painel Super Admin: cria a conta de super administrador (tabela super_admin_users).
|   php artisan super-admin:create                     (usa SUPER_ADMIN_NAME/EMAIL/PASSWORD do .env)
|   php artisan super-admin:create --email=x@y.mz --name="Nome" --password="..."
|   php artisan super-admin:create --reset             (redefine a password de uma conta existente)
| Sem password indicada é gerada uma password forte e mostrada no ecrã.
*/
Artisan::command('super-admin:create {--name=} {--email=} {--password=} {--reset : Redefine a password se a conta já existir}', function () {
    $account = config('super_admin.account');
    $name = $this->option('name') ?: ($account['name'] ?? 'Super Administrator');
    $email = $this->option('email') ?: ($account['email'] ?? null);
    $password = $this->option('password') ?: ($account['password'] ?? null);

    if (! $email) {
        $this->error('Indique o email com --email ou SUPER_ADMIN_EMAIL no .env.');

        return 1;
    }

    $existing = \App\Models\SuperAdminUser::where('email', $email)->first();

    if ($existing && ! $this->option('reset')) {
        $this->info("A conta Super Admin {$email} já existe (nada alterado).");

        return 0;
    }

    $generated = false;
    if (! $password) {
        $password = \Illuminate\Support\Str::password(16);
        $generated = true;
    }

    if ($existing) {
        $existing->update(['password' => $password, 'failed_attempts' => 0, 'locked_until' => null, 'status' => 'active']);
        $this->info("Password da conta Super Admin {$email} redefinida.");
    } else {
        \App\Models\SuperAdminUser::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'status' => 'active',
            'two_factor_enabled' => false,
        ]);
        $this->info("Conta Super Admin criada: {$email}");
    }

    if ($generated) {
        $this->warn("Password gerada: {$password}");
        $this->warn('Guarde-a agora e altere-a depois do primeiro login.');
    }

    return 0;
})->purpose('Cria (ou redefine) a conta do painel Super Admin');
