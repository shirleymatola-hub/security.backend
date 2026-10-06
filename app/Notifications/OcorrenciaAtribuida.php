<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OcorrenciaAtribuida extends Notification
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
        return [
            'title' => 'Nova ocorrência atribuída a si',
            'message' => 'Uma ocorrência foi atribuída para si. Verifique os detalhes e assuma a investigação.',
            'incident_id' => $this->incident->id,
            'reference_code' => $this->incident->reference_code,
            'category' => $this->incident->category->name ?? '-',
            'priority' => $this->incident->priority,
            'address' => $this->incident->address_detail ?? $this->incident->neighborhood ?? '-',
            'description' => $this->incident->description,
            'incident_date' => $this->incident->incident_date?->format('d/m/Y H:i'),
            'url' => $this->getRedirectUrl($notifiable),
        ];
    }

    public function getUrl(): string
    {
        return $this->getRedirectUrl(auth()->user());
    }

    protected function getRedirectUrl(object $notifiable): string
    {
        if ($notifiable->hasRole('police')) {
            return '/police/incidents/' . $this->incident->id;
        }

        return '/';
    }
}
