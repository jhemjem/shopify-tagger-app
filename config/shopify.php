<?php

return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'shop_domain' => env('SHOPIFY_SHOP_DOMAIN'),
    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
    'api_version' => env('SHOPIFY_API_VERSION', '2024-10'),
    'scopes' => 'read_products,write_products',
];
