<?php

namespace App\Observers;

use App\Models\SuperAdminUser;
use App\Services\SuperAdmin\SuperAdminAuditService;

class SuperAdminUserObserver
{
    /** Colunas atualizadas a cada login (não são uma alteração de perfil). */
    private const LOGIN_TRACKING = ['last_login_at', 'last_login_ip', 'failed_attempts', 'locked_until'];

    public function updated(SuperAdminUser $user): void
    {
        $changed = $user->getChanges();
        unset($changed['updated_at']);

        if (empty($changed)) {
            return;
        }

        $old = [];
        foreach (array_keys($changed) as $key) {
            $old[$key] = $user->getOriginal($key);
        }

        if (array_key_exists('password', $changed)) {
            $event = 'password_changed';
            $desc = "Palavra-passe alterada: {$user->email}";
            unset($changed['password'], $old['password']);
        } elseif (array_key_exists('two_factor_enabled', $changed)) {
            $event = $changed['two_factor_enabled'] ? 'two_factor_enabled' : 'two_factor_disabled';
            $desc = $changed['two_factor_enabled']
                ? "Autenticação de dois fatores ativada: {$user->email}"
                : "Autenticação de dois fatores desativada: {$user->email}";
        } elseif (array_key_exists('locked_until', $changed) && $changed['locked_until'] !== null) {
            $event = 'account_locked';
            $desc = "Conta bloqueada por múltiplas tentativas falhadas: {$user->email}";
        } elseif (array_key_exists('locked_until', $changed) && $old['locked_until'] !== null) {
            $event = 'account_unlocked';
            $desc = "Conta desbloqueada: {$user->email}";
        } elseif (empty(array_diff(array_keys($changed), self::LOGIN_TRACKING))) {
            return;
        } else {
            $event = 'profile_updated';
            $desc = "Perfil atualizado: {$user->email}";
        }

        SuperAdminAuditService::log(
            event: $event,
            entityType: SuperAdminUser::class,
            entityId: $user->id,
            description: $desc,
            oldValues: $old ?: null,
            newValues: $changed ?: null,
        );
    }
}
