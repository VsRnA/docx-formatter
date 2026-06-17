<?php

namespace App\Services\Ai;

use App\Domain\Docx\Port\TranslatorPort;

/**
 * Passthrough / stub translator — no Yandex AI calls.
 */
class MockTranslationService implements TranslatorPort
{
    public function translate(string $text, string $from = 'en', string $to = 'ru'): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (! config('services.mock.translate_enabled', false)) {
            return $text;
        }

        $prefix = config('services.mock.translate_prefix', '[RU] ');

        return $prefix.$text;
    }

    public function translateMany(array $texts, string $from = 'en', string $to = 'ru'): array
    {
        return array_map(fn (string $text): string => $this->translate($text, $from, $to), $texts);
    }
}
