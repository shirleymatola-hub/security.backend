<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'openrouteservice' => [
        'api_key' => env('OPENROUTESERVICE_API_KEY'),
    ],

    /*
    | Envio de SMS (alertas de ocorrências). SMS_DRIVER:
    |   log            → não envia; regista a mensagem no log (predefinição, para desenvolvimento)
    |   twilio         → Twilio (TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM)
    |   africastalking → Africa's Talking (AFRICASTALKING_USERNAME — "sandbox" para testes —,
    |                    AFRICASTALKING_API_KEY, AFRICASTALKING_FROM opcional)
    |   http           → gateway HTTP genérico (ex.: fornecedor moçambicano): POST JSON para
    |                    SMS_HTTP_URL com {to, message}; nomes dos campos e token configuráveis.
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '258'),

        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],

        'africastalking' => [
            'username' => env('AFRICASTALKING_USERNAME'),
            'api_key' => env('AFRICASTALKING_API_KEY'),
            'from' => env('AFRICASTALKING_FROM'),
        ],

        'http' => [
            'url' => env('SMS_HTTP_URL'),
            'token' => env('SMS_HTTP_TOKEN'),
            'to_field' => env('SMS_HTTP_TO_FIELD', 'to'),
            'message_field' => env('SMS_HTTP_MESSAGE_FIELD', 'message'),
            'sender_field' => env('SMS_HTTP_SENDER_FIELD', 'from'),
            'sender' => env('SMS_HTTP_SENDER'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/auth/google/callback'),
    ],

];