<?php

return [
    'oasis' => [
        'url' => 'https://api.oasiscatalog.com',
        'key' => env('OASIS_API_KEY'),
        'currency' => 'kzt',
        'page_size' => 1000,
        'request_interval_ms' => env('OASIS_REQUEST_INTERVAL_MS', 1100),
    ],
    'arte' => [
        'products_url' => env('ARTE_PRODUCTS_URL', 'https://art24.by/capi_v100_xmls/products_description_xml_kz_cdata002.xml'),
        'stock_url' => env('ARTE_STOCK_URL', 'https://art24.by/capi_v100_xmls/minskstocks_kz_002.xml'),
        'username' => env('ARTE_USERNAME'),
        'password' => env('ARTE_PASSWORD'),
    ],
    'markup_percent' => (float) env('CATALOG_MARKUP_PERCENT', 0),
];
