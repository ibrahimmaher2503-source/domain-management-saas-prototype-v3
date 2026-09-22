<?php

return [
    // Only documented, single-domain DV codes are supported in milestone 1.
    'products' => [
        'dv' => [
            'provider_product' => env('SSL_DV_PRODUCT_CODE', 'RapidSSL'),
            'name' => 'Domain Validation SSL',
            'validation_type' => 'DV',
            'validity_options' => [12, 24, 36, 48],
            'customer_price' => env('SSL_DV_CUSTOMER_PRICE'),
            'currency' => env('SSL_DV_CURRENCY'),
            'enabled' => env('SSL_DV_ENABLED', false),
        ],
    ],
    'web_server_type' => env('SSL_WEB_SERVER_TYPE', 'apacheopenssl'),
];
