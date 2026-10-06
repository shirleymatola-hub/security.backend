<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditService;

class UserObserver
{
    public function created(User $user): void
    {
        AuditService::log(
            event: 'user_created',
            entityType: User::class,
            entityId: $user->id,
            description: "Conta criada: {$user->name} ({$user->email})",
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'police_station_id' => $user->police_station_id,
                'district_id' => $user->district_id,
            ],
        );
    }

    public function updated(User $user): void
    {
        $changed = $user->getDirty();
        unset($changed['updated_at']);

        if (empty($changed)) {
            return;
        }

        $old = [];
        foreach ($changed as $key => $newVal) {
            $old[$key] = $user->getOriginal($key);
        }

        if (isset($changed['status'])) {
            $event = $changed['status'] === 'active' ? 'user_activated' : 'user_deactivated';
            $desc = "Estado alterado de '{$old['status']}' para '{$changed['status']}'";
        } elseif (isset($changed['password'])) {
            $event = 'password_changed';
            $desc = 'Palavra-passe alterada';
            unset($changed['password']);
            unset($old['password']);
        } elseif (isset($changed['locked_until'])) {
            if ($changed['locked_until'] === null && $old['locked_until'] !== null) {
                $event = 'account_unlocked';
                $desc = "Conta desbloqueada: {$user->email}";
            } elseif ($changed['locked_until'] !== null) {
                $event = 'account_locked';
                $desc = "Conta bloqueada por múltiplas tentativas falhadas: {$user->email}";
            } else {
                return;
            }
        } else {
            $event = 'user_updated';
            $desc = 'Dados do utilizador alterados';
        }

        AuditService::log(
            event: $event,
            entityType: User::class,
            entityId: $user->id,
            description: $desc,
            oldValues: $old ?: null,
            newValues: $changed ?: null,
        );
    }

    public function deleted(User $user): void
    {
        AuditService::log(
            event: 'user_deleted',
            entityType: User::class,
            entityId: $user->id,
            description: "Conta eliminada: {$user->name} ({$user->email})",
        );
    }
}
