<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AtualizacaoOcorrencia extends Notification
{
    use Queueable;

    protected string $action;
    protected ?string $observation;

    public function __construct(
        public Incident $incident,
        string $action,
        ?string $observation = null
    ) {
        $this->action = $action;
        $this->observation = $observation;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusLabels = [
            'pending' => 'Pendente',
            'investigating' => 'Em investigação',
            'resolved' => 'Resolvida',
            'archived' => 'Arquivada',
        ];

        $actionMessages = [
            'status_changed' => 'O estado da ocorrência foi alterado para "' . ($statusLabels[$this->incident->status] ?? $this->incident->status) . '".',
            'resolved' => 'A ocorrência foi marcada como resolvida.',
            'priority_changed' => 'A prioridade da ocorrência foi alterada.',
        ];

        return [
            'title' => 'Atualização na ocorrência ' . $this->incident->reference_code,
            'message' => $actionMessages[$this->action] ?? 'A ocorrência foi atualizada.',
            'incident_id' => $this->incident->id,
            'reference_code' => $this->incident->reference_code,
            'category' => $this->incident->category->name ?? '-',
            'priority' => $this->incident->priority,
            'status' => $this->incident->status,
            'address' => $this->incident->address_detail ?? $this->incident->neighborhood ?? '-',
            'observation' => $this->observation,
            'action' => $this->action,
            'url' => $this->getRedirectUrl($notifiable),
        ];
    }

    public function getUrl(): string
    {
        return $this->getRedirectUrl(auth()->user());
    }

    protected function getRedirectUrl(object $notifiable): string
    {
        if ($notifiable->hasRole('post_commander')) {
            return '/post-commander/incidents/' . $this->incident->id;
        }
        if ($notifiable->hasRole('squad_commander')) {
            return '/squad-commander/incidents/' . $this->incident->id;
        }
        if ($notifiable->hasRole('district_commander')) {
            return '/district-commander/incidents/' . $this->incident->id;
        }
        if ($notifiable->hasRole('manager')) {
            return '/manager/incidents/' . $this->incident->id;
        }
        if ($notifiable->hasRole('police')) {
            return '/police/incidents/' . $this->incident->id;
        }

        return '/';
    }
}
