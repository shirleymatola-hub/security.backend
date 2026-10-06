<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OcorrenciaEncaminhada extends Notification
{
    use Queueable;

    protected ?string $reason;

    public function __construct(
        public Incident $incident,
        ?string $reason = null
    ) {
        $this->reason = $reason;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Ocorrência encaminhada para investigação',
            'message' => 'Uma ocorrência foi encaminhada para o SERNIC para investigação criminal.',
            'incident_id' => $this->incident->id,
            'reference_code' => $this->incident->reference_code,
            'category' => $this->incident->category->name ?? '-',
            'priority' => $this->incident->priority,
            'address' => $this->incident->address_detail ?? $this->incident->neighborhood ?? '-',
            'description' => $this->incident->description,
            'reason' => $this->reason,
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
        if ($notifiable->hasRole('sernic_officer')) {
            return '/sernic/incidents/' . $this->incident->id;
        }

        return '/';
    }
}
