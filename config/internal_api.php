<?php

return [

    'enabled' => env('INTERNAL_API_ENABLED', false),

    'allowed_ips' => array_filter(explode(',', env('INTERNAL_API_ALLOWED_IPS', '127.0.0.1'))),

    'rate_limit' => [
        'attempts' => (int) env('INTERNAL_API_RATE_LIMIT', 100),
        'decay_minutes' => (int) env('INTERNAL_API_RATE_LIMIT_DECAY', 1),
    ],

    'token_default_expiry_days' => (int) env('INTERNAL_API_TOKEN_EXPIRY_DAYS', 90),

];
