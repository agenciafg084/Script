<?php

return [
    'base_url' => env('ASAAS_ENV', 'sandbox') === 'production' 
        ? 'https://api.asaas.com/v3' 
        : 'https://sandbox.asaas.com/api/v3',
    'api_key' => env('ASAAS_API_KEY'),
    'env' => env('ASAAS_ENV', 'sandbox'),
    'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
];
