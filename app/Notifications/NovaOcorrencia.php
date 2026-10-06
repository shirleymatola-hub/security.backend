<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NovaOcorrencia extends Notification
{
    use Queueable;

    public function __construct(
        public Incident $incident
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $reporter = $this->incident->reporter;

        return [
            'title' => 'Nova ocorrência recebida',
            'message' => 'Uma nova denúncia foi registada e atribuída à sua unidade.',
            'incident_id' => $this->incident->id,
            'reference_code' => $this->incident->reference_code,
            'category' => $this->incident->category->name ?? '-',
            'priority' => $this->incident->priority,
            'address' => $this->incident->address_detail ?? $this->incident->neighborhood ?? '-',
            'incident_date' => $this->incident->incident_date?->format('d/m/Y H:i'),
            'reported_by' => $this->incident->is_anonymous ? 'Anónimo' : ($reporter->name ?? '-'),
            'url' => $this->getRedirectUrl($notifiable),
        ];
    }

    public function getUrl(): string
    {
        return $this->getRedirectUrl(auth()->user());
    }

    protected function getRedirectUrl(object $notifiable): string
    {
        if ($notifiable->hasRole('admin')) {
            return '/admin/users';
        }
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

        return '/';
    }
}
