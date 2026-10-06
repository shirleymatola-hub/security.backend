<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\User;
use App\Models\PoliceStation;
use App\Enums\IncidentStatus;
use App\Enums\Priority;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReminderService
{
    public function getReminders(User $user): array
    {
        $reminders = match (true) {
            $user->hasRole('admin')               => $this->getAdminReminders($user),
            $user->hasRole('manager')             => $this->getManagerReminders($user),
            $user->hasRole('post_commander')      => $this->getPostCommanderReminders($user),
            $user->hasRole('squad_commander')     => $this->getSquadCommanderReminders($user),
            $user->hasRole('district_commander')  => $this->getDistrictCommanderReminders($user),
            $user->hasRole('police')              => $this->getPoliceReminders($user),
            $user->hasRole('sernic_officer')      => $this->getSernicReminders($user),
            default                               => [],
        };

        $reminders = $this->deduplicateReminders($reminders);

        return [
            'reminders' => $reminders,
            'total' => count($reminders),
            'urgent' => count(array_filter($reminders, fn($r) => $r['priority'] === 'urgent')),
            'high' => count(array_filter($reminders, fn($r) => $r['priority'] === 'high')),
            'medium' => count(array_filter($reminders, fn($r) => $r['priority'] === 'medium')),
            'info' => count(array_filter($reminders, fn($r) => $r['priority'] === 'info')),
        ];
    }

    private function deduplicateReminders(array $reminders): array
    {
        $seen = [];
        $deduplicated = [];

        foreach ($reminders as $reminder) {
            $key = $reminder['id'] ?? md5($reminder['title'] . $reminder['priority']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $deduplicated[] = $reminder;
            }
        }

        return $deduplicated;
    }

    private function getAdminReminders(User $user): array
    {
        $reminders = [];

        $totalPending = Incident::where('status', IncidentStatus::PENDING->value)->count();
        if ($totalPending > 0) {
            $reminders[] = [
                'id' => 'admin-pending-count',
                'title' => "{$totalPending} ocorrências pendentes no sistema",
                'message' => 'Existem ocorrências aguardando análise ou atribuição.',
                'priority' => 'medium',
                'icon' => 'pending_actions',
                'url' => '/',
                'date' => null,
            ];
        }

        $urgentCount = Incident::where('priority', Priority::URGENT->value)
            ->where('status', '!=', IncidentStatus::RESOLVED->value)
            ->where('status', '!=', IncidentStatus::ARCHIVED->value)
            ->count();
        if ($urgentCount > 0) {
            $reminders[] = [
                'id' => 'admin-urgent-count',
                'title' => "{$urgentCount} ocorrências urgentes ativas",
                'message' => 'Ocorrências urgentes que precisam de atenção imediata.',
                'priority' => 'urgent',
                'icon' => 'warning',
                'url' => '/',
                'date' => null,
            ];
        }

        $staleIncidents = Incident::where('status', IncidentStatus::PENDING->value)
            ->where('created_at', '<=', Carbon::now()->subHours(48))
            ->orderBy('created_at', 'asc')
            ->first();
        if ($staleIncidents) {
            $hours = $staleIncidents->created_at->diffInHours(now());
            $reminders[] = [
                'id' => 'admin-stale-' . $staleIncidents->id,
                'title' => 'Ocorrência pendente há ' . $hours . ' horas',
                'message' => $staleIncidents->reference_code . ' — ' . $staleIncidents->title,
                'priority' => 'high',
                'icon' => 'schedule',
                'url' => '/',
                'date' => $staleIncidents->created_at->format('d/m/Y — H:i'),
            ];
        }

        return $reminders;
    }

    private function getManagerReminders(User $user): array
    {
        $reminders = [];
        $stationId = $user->police_station_id;
        if (!$stationId) return $reminders;

        $pending = Incident::where('police_station_id', $stationId)
            ->where('status', IncidentStatus::PENDING->value)
            ->whereNull('assigned_to')
            ->count();
        if ($pending > 0) {
            $reminders[] = [
                'id' => 'manager-unassigned-count',
                'title' => "{$pending} ocorrências sem agente atribuído",
                'message' => 'Ocorrências que ainda não foram atribuídas a um agente.',
                'priority' => 'high',
                'icon' => 'person_add',
                'url' => '/manager/incidents',
                'date' => null,
            ];
        }

        $urgent = Incident::where('police_station_id', $stationId)
            ->where('priority', Priority::URGENT->value)
            ->where('status', '!=', IncidentStatus::RESOLVED->value)
            ->where('status', '!=', IncidentStatus::ARCHIVED->value)
            ->count();
        if ($urgent > 0) {
            $reminders[] = [
                'id' => 'manager-urgent-count',
                'title' => "{$urgent} ocorrências urgentes",
                'message' => 'Ocorrências urgentes que precisam de tratamento imediato.',
                'priority' => 'urgent',
                'icon' => 'warning',
                'url' => '/manager/incidents',
                'date' => null,
            ];
        }

        $stalePending = Incident::where('police_station_id', $stationId)
            ->where('status', IncidentStatus::PENDING->value)
            ->whereNull('assigned_to')
            ->where('created_at', '<=', Carbon::now()->subHours(24))
            ->orderBy('created_at', 'asc')
            ->first();
        if ($stalePending) {
            $hours = $stalePending->created_at->diffInHours(now());
            $reminders[] = [
                'id' => 'manager-stale-' . $stalePending->id,
                'title' => 'Ocorrência pendente há ' . $hours . ' horas',
                'message' => $stalePending->reference_code . ' — ' . $stalePending->title,
                'priority' => 'medium',
                'icon' => 'schedule',
                'url' => '/manager/incidents/' . $stalePending->id,
                'date' => $stalePending->created_at->format('d/m/Y — H:i'),
            ];
        }

        return $reminders;
    }

    private function getPostCommanderReminders(User $user): array
    {
        $reminders = [];
        $stationId = $user->police_station_id;
        if (!$stationId) return $reminders;

        $unassigned = Incident::where('police_station_id', $stationId)
            ->where('status', IncidentStatus::PENDING->value)
            ->whereNull('assigned_to')
            ->count();
        if ($unassigned > 0) {
            $reminders[] = [
                'id' => 'post-unassigned-count',
                'title' => "{$unassigned} ocorrências sem agente atribuído",
                'message' => 'Ocorrências que precisam ser atribuídas a um agente.',
                'priority' => 'high',
                'icon' => 'person_add',
                'url' => '/post-commander/incidents',
                'date' => null,
            ];
        }

        $urgent = Incident::where('police_station_id', $stationId)
            ->where('priority', Priority::URGENT->value)
            ->where('status', '!=', IncidentStatus::RESOLVED->value)
            ->where('status', '!=', IncidentStatus::ARCHIVED->value)
            ->count();
        if ($urgent > 0) {
            $reminders[] = [
                'id' => 'post-urgent-count',
                'title' => "{$urgent} ocorrências urgentes",
                'message' => 'Ocorrências urgentes que precisam de atenção imediata.',
                'priority' => 'urgent',
                'icon' => 'warning',
                'url' => '/post-commander/incidents',
                'date' => null,
            ];
        }

        $stale = Incident::where('police_station_id', $stationId)
            ->where('status', IncidentStatus::PENDING->value)
            ->whereNull('assigned_to')
            ->where('created_at', '<=', Carbon::now()->subHours(24))
            ->orderBy('created_at', 'asc')
            ->first();
        if ($stale) {
            $hours = $stale->created_at->diffInHours(now());
            $reminders[] = [
                'id' => 'post-stale-' . $stale->id,
                'title' => 'Ocorrência pendente há ' . $hours . ' horas',
                'message' => $stale->reference_code . ' — ' . $stale->title,
                'priority' => 'medium',
                'icon' => 'schedule',
                'url' => '/post-commander/incidents/' . $stale->id,
                'date' => $stale->created_at->format('d/m/Y — H:i'),
            ];
        }

        return $reminders;
    }

    private function getSquadCommanderReminders(User $user): array
    {
        $reminders = [];
        $stationId = $user->police_station_id;
        if (!$stationId) return $reminders;

        $pending = Incident::where('police_station_id', $stationId)
            ->where('status', IncidentStatus::PENDING->value)
            ->count();
        if ($pending > 0) {
            $reminders[] = [
                'id' => 'squad-pending-count',
                'title' => "{$pending} ocorrências pendentes na esquadra",
                'message' => 'Ocorrências que precisam de acompanhamento.',
                'priority' => 'high',
                'icon' => 'pending_actions',
                'url' => '/squad-commander/incidents',
                'date' => null,
            ];
        }

        $unassigned = Incident::where('police_station_id', $stationId)
            ->where('status', IncidentStatus::PENDING->value)
            ->whereNull('assigned_to')
            ->count();
        if ($unassigned > 0) {
            $reminders[] = [
                'id' => 'squad-unassigned-count',
                'title' => "{$unassigned} ocorrências sem agente atribuído",
                'message' => 'Ocorrências que precisam ser atribuídas.',
                'priority' => 'medium',
                'icon' => 'person_add',
                'url' => '/squad-commander/incidents',
                'date' => null,
            ];
        }

        $stale = Incident::where('police_station_id', $stationId)
            ->where('status', IncidentStatus::PENDING->value)
            ->where('created_at', '<=', Carbon::now()->subHours(48))
            ->orderBy('created_at', 'asc')
            ->first();
        if ($stale) {
            $hours = $stale->created_at->diffInHours(now());
            $reminders[] = [
                'id' => 'squad-stale-' . $stale->id,
                'title' => 'Ocorrência pendente há ' . $hours . ' horas',
                'message' => $stale->reference_code . ' — ' . $stale->title,
                'priority' => 'medium',
                'icon' => 'schedule',
                'url' => '/squad-commander/incidents/' . $stale->id,
                'date' => $stale->created_at->format('d/m/Y — H:i'),
            ];
        }

        return $reminders;
    }

    private function getDistrictCommanderReminders(User $user): array
    {
        $reminders = [];
        $districtId = $user->district_id;
        if (!$districtId) return $reminders;

        $stationIds = PoliceStation::where('district_id', $districtId)->pluck('id');
        if ($stationIds->isEmpty()) return $reminders;

        $pending = Incident::whereIn('police_station_id', $stationIds)
            ->where('status', IncidentStatus::PENDING->value)
            ->whereNull('assigned_to')
            ->count();
        if ($pending > 0) {
            $reminders[] = [
                'id' => 'district-unassigned-count',
                'title' => "{$pending} ocorrências sem agente no distrito",
                'message' => 'Ocorrências aguardando atribuição.',
                'priority' => 'high',
                'icon' => 'person_add',
                'url' => '/district-commander/incidents',
                'date' => null,
            ];
        }

        $urgent = Incident::whereIn('police_station_id', $stationIds)
            ->where('priority', Priority::URGENT->value)
            ->where('status', '!=', IncidentStatus::RESOLVED->value)
            ->where('status', '!=', IncidentStatus::ARCHIVED->value)
            ->count();
        if ($urgent > 0) {
            $reminders[] = [
                'id' => 'district-urgent-count',
                'title' => "{$urgent} ocorrências urgentes no distrito",
                'message' => 'Ocorrências urgentes que precisam de atenção imediata.',
                'priority' => 'urgent',
                'icon' => 'warning',
                'url' => '/district-commander/incidents',
                'date' => null,
            ];
        }

        $stale = Incident::whereIn('police_station_id', $stationIds)
            ->where('status', IncidentStatus::PENDING->value)
            ->where('created_at', '<=', Carbon::now()->subHours(48))
            ->orderBy('created_at', 'asc')
            ->first();
        if ($stale) {
            $hours = $stale->created_at->diffInHours(now());
            $reminders[] = [
                'id' => 'district-stale-' . $stale->id,
                'title' => 'Ocorrência pendente há ' . $hours . ' horas',
                'message' => $stale->reference_code . ' — ' . $stale->title,
                'priority' => 'medium',
                'icon' => 'schedule',
                'url' => '/district-commander/incidents/' . $stale->id,
                'date' => $stale->created_at->format('d/m/Y — H:i'),
            ];
        }

        return $reminders;
    }

    private function getPoliceReminders(User $user): array
    {
        $reminders = [];

        $myPending = Incident::where('assigned_to', $user->id)
            ->where('status', IncidentStatus::PENDING->value)
            ->count();
        if ($myPending > 0) {
            $reminders[] = [
                'id' => 'police-pending-count',
                'title' => "{$myPending} ocorrências atribuídas pendentes",
                'message' => 'Ocorrências que lhe foram atribuídas e aguardam ação.',
                'priority' => 'high',
                'icon' => 'assignment',
                'url' => '/police/incidents',
                'date' => null,
            ];
        }

        $myUrgent = Incident::where('assigned_to', $user->id)
            ->where('priority', Priority::URGENT->value)
            ->where('status', '!=', IncidentStatus::RESOLVED->value)
            ->where('status', '!=', IncidentStatus::ARCHIVED->value)
            ->count();
        if ($myUrgent > 0) {
            $reminders[] = [
                'id' => 'police-urgent-count',
                'title' => "{$myUrgent} ocorrências urgentes atribuídas",
                'message' => 'Ocorrências urgentes que precisam de atenção imediata.',
                'priority' => 'urgent',
                'icon' => 'warning',
                'url' => '/police/incidents',
                'date' => null,
            ];
        }

        $stale = Incident::where('assigned_to', $user->id)
            ->where('status', IncidentStatus::PENDING->value)
            ->where('created_at', '<=', Carbon::now()->subHours(24))
            ->orderBy('created_at', 'asc')
            ->first();
        if ($stale) {
            $hours = $stale->created_at->diffInHours(now());
            $reminders[] = [
                'id' => 'police-stale-' . $stale->id,
                'title' => 'Ocorrência pendente há ' . $hours . ' horas',
                'message' => $stale->reference_code . ' — ' . $stale->title,
                'priority' => 'medium',
                'icon' => 'schedule',
                'url' => '/police/incidents/' . $stale->id,
                'date' => $stale->created_at->format('d/m/Y — H:i'),
            ];
        }

        $myInvestigating = Incident::where('assigned_to', $user->id)
            ->where('status', IncidentStatus::INVESTIGATING->value)
            ->where('updated_at', '<=', Carbon::now()->subDays(3))
            ->orderBy('updated_at', 'asc')
            ->first();
        if ($myInvestigating) {
            $days = $myInvestigating->updated_at->diffInDays(now());
            $reminders[] = [
                'id' => 'police-stale-investigating-' . $myInvestigating->id,
                'title' => 'Ocorrência em acompanhamento há ' . $days . ' dias',
                'message' => $myInvestigating->reference_code . ' — ' . $myInvestigating->title,
                'priority' => 'info',
                'icon' => 'autorenew',
                'url' => '/police/incidents/' . $myInvestigating->id,
                'date' => $myInvestigating->updated_at->format('d/m/Y — H:i'),
            ];
        }

        return $reminders;
    }

    private function getSernicReminders(User $user): array
    {
        $reminders = [];

        $forwarded = Incident::where('status', IncidentStatus::INVESTIGATING->value)
            ->where('assigned_to', $user->id)
            ->count();
        if ($forwarded > 0) {
            $reminders[] = [
                'id' => 'sernic-forwarded-count',
                'title' => "{$forwarded} ocorrências para investigação",
                'message' => 'Ocorrências encaminhadas ao SERNIC que precisam de acompanhamento.',
                'priority' => 'high',
                'icon' => 'search',
                'url' => '/sernic/incidents',
                'date' => null,
            ];
        }

        $recentForwarded = Incident::where('assigned_to', $user->id)
            ->where('status', IncidentStatus::INVESTIGATING->value)
            ->where('updated_at', '>=', Carbon::now()->subHours(48))
            ->orderBy('updated_at', 'desc')
            ->first();
        if ($recentForwarded) {
            $reminders[] = [
                'id' => 'sernic-recent-' . $recentForwarded->id,
                'title' => 'Ocorrência recebida recentemente',
                'message' => $recentForwarded->reference_code . ' — ' . $recentForwarded->title,
                'priority' => 'info',
                'icon' => 'new_releases',
                'url' => '/sernic/incidents/' . $recentForwarded->id,
                'date' => $recentForwarded->updated_at->format('d/m/Y — H:i'),
            ];
        }

        $stale = Incident::where('assigned_to', $user->id)
            ->where('status', IncidentStatus::INVESTIGATING->value)
            ->where('updated_at', '<=', Carbon::now()->subDays(7))
            ->orderBy('updated_at', 'asc')
            ->first();
        if ($stale) {
            $days = $stale->updated_at->diffInDays(now());
            $reminders[] = [
                'id' => 'sernic-stale-' . $stale->id,
                'title' => 'Investigação pendente há ' . $days . ' dias',
                'message' => $stale->reference_code . ' — ' . $stale->title,
                'priority' => 'medium',
                'icon' => 'schedule',
                'url' => '/sernic/incidents/' . $stale->id,
                'date' => $stale->updated_at->format('d/m/Y — H:i'),
            ];
        }

        return $reminders;
    }
}
