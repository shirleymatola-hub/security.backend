<?php

namespace App\Notifications\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;

/**
 * Canal de notificação "SMS": usa toSms() da notificação e o telefone do destinatário
 * (routeNotificationForSms do modelo User).
 */
class SmsChannel
{
    public function __construct(
        protected SmsService $sms
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $to = SmsService::normalizePhone($notifiable->routeNotificationFor('sms', $notification));

        if ($to === null || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->sms->send($to, $notification->toSms($notifiable));
    }
}
