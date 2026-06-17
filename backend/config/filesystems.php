<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
        ],
        'yc' => [
            'driver' => 's3',
            'key' => env('YC_STORAGE_KEY'),
            'secret' => env('YC_STORAGE_SECRET'),
            'region' => env('YC_STORAGE_REGION', 'ru-central1'),
            'bucket' => env('YC_STORAGE_BUCKET'),
            'url' => env('YC_STORAGE_URL'),
            'endpoint' => env('YC_STORAGE_ENDPOINT', 'https://storage.yandexcloud.net'),
            'use_path_style_endpoint' => true,
            'throw' => true,
        ],
    ],
];
