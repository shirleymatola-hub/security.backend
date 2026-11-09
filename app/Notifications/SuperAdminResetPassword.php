<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Link de recuperação de palavra-passe do painel Super Admin. */
class SuperAdminResetPassword extends Notification
{
    public function __construct(private string $url, private int $minutes)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Recuperação de password — Super Admin SMP')
            ->greeting("Olá {$notifiable->name},")
            ->line('Recebemos um pedido para redefinir a password da sua conta de Super Administrador.')
            ->action('Redefinir Password', $this->url)
            ->line("Este link é válido durante {$this->minutes} minutos.")
            ->line('Se não fez este pedido, ignore este email.');
    }
}
