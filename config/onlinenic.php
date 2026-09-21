<?php

return [
    'host' => env('ONLINENIC_HOST', 'ote.onlinenic.com'),
    'port' => (int) env('ONLINENIC_PORT', 30009),
    'client_id' => env('ONLINENIC_CLIENT_ID'),
    'password' => env('ONLINENIC_PASSWORD'),
    'connect_timeout' => (float) env('ONLINENIC_CONNECT_TIMEOUT', 10),
    'read_timeout' => (float) env('ONLINENIC_READ_TIMEOUT', 30),
    'account_currency' => env('ONLINENIC_ACCOUNT_CURRENCY'),
    'customer_billing_currency' => env('CUSTOMER_BILLING_CURRENCY'),
    'session_request_limit' => 150,
];
