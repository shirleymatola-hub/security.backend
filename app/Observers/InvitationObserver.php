<?php

namespace App\Observers;

use App\Models\InternalUserInvitation;
use App\Services\AuditService;

class InvitationObserver
{
    public function created(InternalUserInvitation $invitation): void
    {
        AuditService::log(
            event: 'invitation_created',
            entityType: InternalUserInvitation::class,
            entityId: $invitation->id,
            description: "Convite criado para {$invitation->name} ({$invitation->email}) — role: {$invitation->role}",
            newValues: [
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'police_station_id' => $invitation->police_station_id,
                'district_id' => $invitation->district_id,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ],
        );
    }

    public function updated(InternalUserInvitation $invitation): void
    {
        $changed = $invitation->getDirty();
        unset($changed['updated_at']);

        if (isset($changed['status'])) {
            $old = $invitation->getOriginal('status');
            $new = $changed['status'];

            $event = match ($new) {
                'accepted' => 'invitation_accepted',
                'cancelled' => 'invitation_cancelled',
                default => 'invitation_status_changed',
            };

            $desc = match ($new) {
                'accepted' => "Convite aceite por {$invitation->email}",
                'cancelled' => "Convite cancelado para {$invitation->email}",
                default => "Estado do convite alterado de '{$old}' para '{$new}'",
            };

            AuditService::log(
                event: $event,
                entityType: InternalUserInvitation::class,
                entityId: $invitation->id,
                description: $desc,
                oldValues: ['status' => $old],
                newValues: ['status' => $new],
            );
        }
    }
}
