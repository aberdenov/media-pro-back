<?php

return [

    'paths' => [
        'api/*',
        'catalog/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://mediapro.kz',
        'https://www.mediapro.kz',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];