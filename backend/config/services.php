<?php

return [
    'integrations' => [
        /** Local disk instead of Yandex Object Storage */
        'mock_storage' => filter_var(env('MOCK_STORAGE', env('MOCK_YANDEX', false)), FILTER_VALIDATE_BOOLEAN),
        /** Passthrough translator instead of Yandex AI Studio */
        'mock_translation' => filter_var(env('MOCK_TRANSLATION', env('MOCK_YANDEX', false)), FILTER_VALIDATE_BOOLEAN),
    ],

    'mock' => [
        'storage_path' => env('MOCK_STORAGE_PATH', 'mock-cloud'),
        /** When false, translator returns original EN text unchanged */
        'translate_enabled' => filter_var(env('MOCK_TRANSLATE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'translate_prefix' => env('MOCK_TRANSLATE_PREFIX', '[RU] '),
    ],

    'yandex_ai' => [
        'api_key' => env('YANDEX_AI_API_KEY'),
        'folder_id' => env('YANDEX_AI_FOLDER_ID'),
        'translate_model' => env('YANDEX_AI_TRANSLATE_MODEL', 'yandexgpt'),
    ],

    'translation' => [
        /** Optional terminology map (JSON object: {"source term":"target term"}). */
        'glossary' => json_decode((string) env('TRANSLATION_GLOSSARY', '{}'), true) ?: [],
    ],

    'docx' => [
        /** Write translated text back into source DOCX during processing */
        'write_translated_docx' => filter_var(env('DOCX_WRITE_TRANSLATED', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
