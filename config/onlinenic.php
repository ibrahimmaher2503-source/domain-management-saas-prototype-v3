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
    'ote' => [
        'allow_writes' => filter_var(env('ONLINENIC_OTE_ALLOW_WRITES', false), FILTER_VALIDATE_BOOL),
        'confirm' => env('ONLINENIC_OTE_CONFIRM'),
        'test_domain' => env('ONLINENIC_OTE_TEST_DOMAIN'),
        'test_domain_password' => env('ONLINENIC_OTE_TEST_DOMAIN_PASSWORD'),
        'test_nameserver_1' => env('ONLINENIC_OTE_TEST_NAMESERVER_1'),
        'test_nameserver_2' => env('ONLINENIC_OTE_TEST_NAMESERVER_2'),
    ],
];
