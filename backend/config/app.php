<?php

return [
    'name' => env('APP_NAME', 'DocxFormatter'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'max_upload_mb' => (int) env('MAX_UPLOAD_MB', 150),
    // Skip base64 inlining for larger images when preparing published/export HTML.
    'published_inline_image_max_mb' => (int) env('PUBLISHED_INLINE_IMAGE_MAX_MB', 2),
    // Total raw image bytes to inline per HTML prepare call (rest keep URLs).
    'published_inline_image_total_budget_mb' => (int) env('PUBLISHED_INLINE_IMAGE_TOTAL_BUDGET_MB', 32),
    'timezone' => 'UTC',
    'locale' => 'ru',
    'fallback_locale' => 'en',
    'faker_locale' => 'ru_RU',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
