<?php

return [
    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
    'secret_key' => env('PAYMOB_SECRET_KEY'),
    'public_key' => env('PAYMOB_PUBLIC_KEY'),
    'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
    'card_integration_id' => env('PAYMOB_CARD_INTEGRATION_ID'),
    'mode' => env('PAYMOB_MODE', 'test'),
    'payment_expiration' => (int) env('PAYMOB_PAYMENT_EXPIRATION', 3600),
    'notification_url' => env('PAYMOB_NOTIFICATION_URL'),
    'redirection_url' => env('PAYMOB_REDIRECTION_URL'),
    'connect_timeout' => (float) env('PAYMOB_CONNECT_TIMEOUT', 5),
    'timeout' => (float) env('PAYMOB_TIMEOUT', 15),
];
