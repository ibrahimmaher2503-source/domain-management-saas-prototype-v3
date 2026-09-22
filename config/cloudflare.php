<?php

return [
    'api_base' => env('CLOUDFLARE_API_BASE', 'https://api.cloudflare.com/client/v4'),
    'api_token' => env('CLOUDFLARE_API_TOKEN', ''),
    'account_id' => env('CLOUDFLARE_ACCOUNT_ID', ''),
];
