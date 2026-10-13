<?php

namespace App\Notifications;

use App\Enums\IncidentStatus;
use App\Enums\Priority;
use App\Models\Incident;
use App\Notifications\Channels\SmsChannel;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerta de ocorrência por email e/ou SMS (as notificações dentro do sistema continuam a ser
 * NovaOcorrencia, OcorrenciaAtribuida, ...). Enviado pelo IncidentAlertService, que escolhe os
 * destinatários e os canais. O texto segue o idioma do perfil do destinatário (User::preferredLocale).
 */
class AlertaOcorrencia extends Notification
{
    use Queueable;

    public const NEW = 'new';
    public const URGENT = 'urgent';
    public const ASSIGNED = 'assigned';
    public const STATUS = 'status';

    /** Página da ocorrência no frontend, por perfil. */
    private const ROLE_PATHS = [
        'manager' => '/manager/incidents/',
        'police' => '/police/incidents/',
        'post_commander' => '/post-commander/incidents/',
        'squad_commander' => '/squad-commander/incidents/',
        'district_commander' => '/district-commander/incidents/',
        'sernic_officer' => '/sernic/incidents/',
    ];

    /**
     * @param  string[]  $channels  'mail' e/ou 'sms'
     */
    public function __construct(
        public Incident $incident,
        public string $event,
        public array $channels = ['mail', 'sms'],
        public ?string $observation = null,
    ) {}

    public function via(object $notifiable): array
    {
        $via = [];

        if (in_array('mail', $this->channels, true) && config('alerts.mail') && filled($notifiable->email)) {
            $via[] = 'mail';
        }
        if (in_array('sms', $this->channels, true) && config('alerts.sms') && SmsService::normalizePhone($notifiable->phone)) {
            $via[] = SmsChannel::class;
        }

        return $via;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting(__('Olá :name', ['name' => $notifiable->name]))
            ->line($this->summary());

        if ($this->event === self::URGENT) {
            $mail->error();
        }

        foreach ($this->details() as $label => $value) {
            $mail->line("**{$label}:** {$value}");
        }

        if (filled($this->observation)) {
            $mail->line('**' . __('Observação') . ':** ' . $this->observation);
        }

        return $mail
            ->action(__('Ver ocorrência'), $this->url($notifiable))
            ->salutation(__('SMP — Sistema de Monitoramento Policial'));
    }

    public function toSms(object $notifiable): string
    {
        $params = $this->params();

        $text = match ($this->event) {
            self::URGENT => __('SMP URGENTE: ocorrência :ref (:priority) - :category, :place.', $params),
            self::ASSIGNED => __('SMP: a ocorrência :ref (:priority) foi-lhe atribuída - :category, :place.', $params),
            self::STATUS => __('SMP: a ocorrência :ref passou ao estado ":status".', $params),
            default => __('SMP: nova ocorrência :ref (:priority) - :category, :place.', $params),
        };

        return $text . ' ' . $this->url($notifiable);
    }

    private function subject(): string
    {
        $params = $this->params();

        return match ($this->event) {
            self::URGENT => __('[SMP] URGENTE: ocorrência :ref (:priority)', $params),
            self::ASSIGNED => __('[SMP] Ocorrência :ref atribuída a si', $params),
            self::STATUS => __('[SMP] Ocorrência :ref: :status', $params),
            default => __('[SMP] Nova ocorrência :ref (:priority)', $params),
        };
    }

    private function summary(): string
    {
        $params = $this->params();

        return match ($this->event) {
            self::URGENT => __('Foi registada uma ocorrência de prioridade :priority na área do seu posto. Requer atenção imediata.', $params),
            self::ASSIGNED => __('Foi-lhe atribuída a ocorrência :ref. Verifique os detalhes e dê seguimento.', $params),
            self::STATUS => __('O estado da ocorrência :ref foi alterado para ":status".', $params),
            default => __('Foi registada uma nova ocorrência na área do seu posto (:station).', $params),
        };
    }

    private function details(): array
    {
        $incident = $this->incident;

        return [
            __('Referência') => $incident->reference_code,
            __('Título') => $incident->title ?: '-',
            __('Categoria') => $incident->category->name ?? '-',
            __('Prioridade') => $this->params()['priority'],
            __('Estado') => $this->params()['status'],
            __('Local') => $this->params()['place'],
            __('Data') => $incident->incident_date?->format('d/m/Y H:i') ?? '-',
        ];
    }

    private function params(): array
    {
        $incident = $this->incident;
        $priority = Priority::tryFrom((string) $incident->priority);
        $status = IncidentStatus::tryFrom((string) $incident->status);

        return [
            'ref' => $incident->reference_code,
            'priority' => $priority ? __($priority->label()) : (string) $incident->priority,
            'status' => $status ? __($status->label()) : (string) $incident->status,
            'category' => $incident->category->name ?? __('Sem categoria'),
            'place' => $incident->neighborhood ?: ($incident->address_detail ?: '-'),
            'station' => $incident->policeStation->name ?? '-',
        ];
    }

    private function url(object $notifiable): string
    {
        foreach (self::ROLE_PATHS as $role => $path) {
            if ($notifiable->hasRole($role)) {
                return frontend_url($path . $this->incident->id);
            }
        }

        return frontend_url('/');
    }
}
