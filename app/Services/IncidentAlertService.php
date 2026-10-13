<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\User;
use App\Notifications\AlertaOcorrencia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alertas externos (email/SMS) das ocorrências — ver config/alerts.php.
 *
 * Cada canal é enviado em separado e protegido: um servidor de email ou fornecedor de SMS
 * indisponível fica registado no log, mas nunca interrompe o pedido que criou/atualizou a
 * ocorrência (em produção não há fila: o envio acontece durante o pedido).
 */
class IncidentAlertService
{
    /** Nova ocorrência: Chefe(s) de Permanência do posto; se alta/urgente, também o Comandante do Posto. */
    public static function newIncident(Incident $incident): void
    {
        $station = $incident->policeStation;
        if (! $station) {
            return;
        }

        static::send(static::managers($incident), $incident, AlertaOcorrencia::NEW, ['mail', 'sms']);

        if (in_array($incident->priority, ['high', 'urgent'], true) && $station->commander_id) {
            static::send(
                User::whereKey($station->commander_id)->get(),
                $incident,
                AlertaOcorrencia::URGENT,
                ['mail', 'sms'],
            );
        }
    }

    /** Ocorrência atribuída: o agente. */
    public static function assigned(Incident $incident, User $officer): void
    {
        static::send(collect([$officer]), $incident, AlertaOcorrencia::ASSIGNED, ['mail', 'sms']);
    }

    /** Mudança de estado: Chefe(s) de Permanência e agente atribuído (só email), exceto quem fez a alteração. */
    public static function statusChanged(Incident $incident, ?string $observation = null): void
    {
        $recipients = static::managers($incident);

        if ($incident->assigned_to) {
            $recipients = $recipients->merge(User::whereKey($incident->assigned_to)->get());
        }

        $recipients = $recipients->reject(fn (User $user) => $user->id === auth()->id());

        static::send($recipients, $incident, AlertaOcorrencia::STATUS, ['mail'], $observation);
    }

    /** Chefes de Permanência (perfil manager) ativos do posto da ocorrência. */
    protected static function managers(Incident $incident): Collection
    {
        if (! $incident->police_station_id) {
            return collect();
        }

        return User::where('police_station_id', $incident->police_station_id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'manager'))
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))
            ->get();
    }

    protected static function send(Collection $users, Incident $incident, string $event, array $channels, ?string $observation = null): void
    {
        foreach ($users->filter()->unique('id') as $user) {
            foreach ($channels as $channel) {
                try {
                    $user->notify(new AlertaOcorrencia($incident, $event, [$channel], $observation));
                } catch (Throwable $e) {
                    Log::warning('Falha ao enviar alerta de ocorrência', [
                        'channel' => $channel,
                        'event' => $event,
                        'incident_id' => $incident->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
