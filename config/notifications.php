<?php

return [
    'idempotency_ttl'    => (int) env('NOTIFICATION_IDEMPOTENCY_TTL', 86400),
    'retry_max_attempts' => (int) env('NOTIFICATION_RETRY_MAX_ATTEMPTS', 5),
    'retry_base_delay'   => (int) env('NOTIFICATION_RETRY_BASE_DELAY', 5),

    'providers' => [
        'sms'   => env('SMS_PROVIDER', 'mock'),
        'email' => env('EMAIL_PROVIDER', 'mock'),
    ],

    'queues' => [
        'high' => 'notifications.transactional',
        'low'  => 'notifications.marketing',
    ],
];
