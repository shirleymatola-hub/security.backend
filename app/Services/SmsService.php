<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Envio de SMS com o fornecedor configurado em services.sms.driver
 * (log, twilio, africastalking ou http). Lança exceção se o fornecedor recusar o envio.
 */
class SmsService
{
    public function send(string $to, string $message): void
    {
        $config = config('services.sms');

        match ($config['driver']) {
            'log' => Log::info("[SMS para {$to}] {$message}"),
            'twilio' => $this->twilio($config['twilio'], $to, $message),
            'africastalking' => $this->africasTalking($config['africastalking'], $to, $message),
            'http' => $this->http($config['http'], $to, $message),
            default => throw new InvalidArgumentException("SMS_DRIVER desconhecido: {$config['driver']}"),
        };
    }

    /**
     * Número em formato internacional (+258841234567). Números locais de 9 dígitos
     * recebem o indicativo de services.sms.default_country_code. Devolve null se inválido.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with(trim($phone), '+')) {
            // já tem indicativo
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (strlen(ltrim($digits, '0')) <= 9) {
            $digits = config('services.sms.default_country_code') . ltrim($digits, '0');
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15 ? '+' . $digits : null;
    }

    private function twilio(array $config, string $to, string $message): void
    {
        Http::asForm()
            ->withBasicAuth((string) $config['sid'], (string) $config['token'])
            ->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$config['sid']}/Messages.json", [
                'To' => $to,
                'From' => $config['from'],
                'Body' => $message,
            ])
            ->throw();
    }

    private function africasTalking(array $config, string $to, string $message): void
    {
        $host = $config['username'] === 'sandbox'
            ? 'https://api.sandbox.africastalking.com'
            : 'https://api.africastalking.com';

        Http::asForm()
            ->withHeaders(['apiKey' => (string) $config['api_key'], 'Accept' => 'application/json'])
            ->timeout(10)
            ->post("{$host}/version1/messaging", array_filter([
                'username' => $config['username'],
                'to' => $to,
                'message' => $message,
                'from' => $config['from'],
            ]))
            ->throw();
    }

    private function http(array $config, string $to, string $message): void
    {
        if (! filled($config['url'])) {
            throw new InvalidArgumentException('SMS_HTTP_URL não definido.');
        }

        $payload = [
            $config['to_field'] => $to,
            $config['message_field'] => $message,
        ];
        if (filled($config['sender'])) {
            $payload[$config['sender_field']] = $config['sender'];
        }

        $request = Http::acceptJson()->timeout(10);
        if (filled($config['token'])) {
            $request = $request->withToken($config['token']);
        }

        $request->post($config['url'], $payload)->throw();
    }
}
