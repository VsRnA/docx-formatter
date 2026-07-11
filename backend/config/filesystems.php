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
        's3' => [
            'driver' => 's3',
            'key' => env('S3_ACCESS_KEY'),
            'secret' => env('S3_SECRET_KEY'),
            'region' => env('S3_REGION', 'ru-1'),
            'bucket' => env('S3_BUCKET'),
            'endpoint' => env('S3_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'root' => env('S3_PATH_PREFIX', 'docx-formatter'),
            'throw' => true,
        ],
    ],
];
