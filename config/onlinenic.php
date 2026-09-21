<?php

return [
    'host' => env('ONLINENIC_HOST', 'ote.onlinenic.com'),
    'port' => (int) env('ONLINENIC_PORT', 30009),
    'client_id' => env('ONLINENIC_CLIENT_ID'),
    'password' => env('ONLINENIC_PASSWORD'),
    'registrant_contact_id' => env('ONLINENIC_REGISTRANT_CONTACT_ID'),
    'admin_contact_id' => env('ONLINENIC_ADMIN_CONTACT_ID'),
    'tech_contact_id' => env('ONLINENIC_TECH_CONTACT_ID'),
    'billing_contact_id' => env('ONLINENIC_BILLING_CONTACT_ID'),
    'connect_timeout' => (float) env('ONLINENIC_CONNECT_TIMEOUT', 10),
    'read_timeout' => (float) env('ONLINENIC_READ_TIMEOUT', 30),
    'account_currency' => env('ONLINENIC_ACCOUNT_CURRENCY'),
    'customer_billing_currency' => env('CUSTOMER_BILLING_CURRENCY'),
    'session_request_limit' => 150,
];
