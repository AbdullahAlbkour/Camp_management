<?php

return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@camp.local'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Camps Management')),
    ],
];
