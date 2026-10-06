<?php

namespace App\Services;

use App\Models\District;
use App\Models\Incident;
use App\Models\PoliceStation;
use App\Models\User;
use App\Notifications\AtualizacaoOcorrencia;
use App\Notifications\NovaOcorrencia;
use App\Notifications\OcorrenciaAtribuida;
use App\Notifications\OcorrenciaEncaminhada;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Notify all commanders in the chain of command for a given station.
     * Hierarchy: Post Commander → Squad Commander → District Commander
     */
    public static function notifyCommanders(Incident $incident, string $notificationClass, mixed ...$args): void
    {
        $recipients = static::getCommandersForIncident($incident);

        foreach ($recipients as $recipient) {
            $recipient->notify(new $notificationClass($incident, ...$args));
        }
    }

    /**
     * Get all commanders in the chain of command for an incident's station.
     */
    public static function getCommandersForIncident(Incident $incident): Collection
    {
        $station = $incident->policeStation;
        if (!$station) {
            return collect();
        }

        $recipients = collect();

        // 1. Post Commander (commander of the station)
        if ($station->commander_id) {
            $commander = User::find($station->commander_id);
            if ($commander) {
                $recipients->push($commander);
            }
        }

        // 2. District Commander (commander of the district the station belongs to)
        if ($station->district_id) {
            $districtCommanders = User::where('district_id', $station->district_id)
                ->whereHas('roles', fn($q) => $q->where('name', 'district_commander'))
                ->get();
            $recipients = $recipients->merge($districtCommanders);
        }

        // 3. Squad Commander (has district_id set, manages the district)
        $squadCommanders = User::where('district_id', $station->district_id)
            ->whereHas('roles', fn($q) => $q->where('name', 'squad_commander'))
            ->get();
        $recipients = $recipients->merge($squadCommanders);

        // 4. Managers at the station
        $managers = User::where('police_station_id', $station->id)
            ->whereHas('roles', fn($q) => $q->where('name', 'manager'))
            ->get();
        $recipients = $recipients->merge($managers);

        return $recipients->filter()->unique('id');
    }

    /**
     * Notify the station commander and managers when a new incident is created.
     */
    public static function notifyNewIncident(Incident $incident): void
    {
        static::notifyCommanders($incident, NovaOcorrencia::class);
    }

    /**
     * Notify the assigned officer.
     */
    public static function notifyAssignedOfficer(Incident $incident, int $officerId): void
    {
        $officer = User::find($officerId);
        if ($officer) {
            $officer->notify(new OcorrenciaAtribuida($incident));
        }
    }

    /**
     * Notify commanders when incident status changes.
     */
    public static function notifyStatusChanged(Incident $incident, ?string $observation = null): void
    {
        static::notifyCommanders($incident, AtualizacaoOcorrencia::class, 'status_changed', $observation);
    }

    /**
     * Notify commanders when incident is resolved.
     */
    public static function notifyResolved(Incident $incident, ?string $observation = null): void
    {
        static::notifyCommanders($incident, AtualizacaoOcorrencia::class, 'resolved', $observation);
    }

    /**
     * Notify SERNIC officer when incident is forwarded.
     */
    public static function notifyForwardedToSernic(Incident $incident, ?string $reason = null): void
    {
        $sernicOfficers = User::whereHas('roles', fn($q) => $q->where('name', 'sernic_officer'))->get();

        foreach ($sernicOfficers as $officer) {
            $officer->notify(new OcorrenciaEncaminhada($incident, $reason));
        }
    }
}
