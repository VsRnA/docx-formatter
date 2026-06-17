<?php

namespace App\Services\Ai;

use App\Domain\Docx\Port\TranslatorPort;
use App\Services\Ai\Support\LanguageDetector;
use App\Services\Ai\Support\TranslationGlossary;
use App\Services\Ai\Support\TranslationResponseSanitizer;

class YandexTranslationService implements TranslatorPort
{
    private const BATCH_ATTEMPTS = 2;

    public function __construct(
        private readonly YandexCompletionClient $client,
        private readonly LanguageDetector $languageDetector,
        private readonly TranslationGlossary $glossary,
        private readonly TranslationResponseSanitizer $sanitizer,
    ) {}

    public function translate(string $text, string $from = 'en', string $to = 'ru'): string
    {
        $text = trim($text);
        if ($text === '' || $this->languageDetector->shouldSkipTranslation($text, $to)) {
            return $text;
        }

        for ($attempt = 0; $attempt < self::BATCH_ATTEMPTS; $attempt++) {
            $temperature = $attempt === 0 ? 0.2 : 0.1;
            $result = $this->client->complete(
                $this->systemPrompt($from, $to, false),
                $text,
                4000,
                $temperature,
            );
            $sanitized = $this->sanitizer->sanitize($result, $text);
            if ($sanitized !== '') {
                return $sanitized;
            }
        }

        return $text;
    }

    public function translateMany(array $texts, string $from = 'en', string $to = 'ru'): array
    {
        if ($texts === []) {
            return [];
        }

        $results = array_fill(0, count($texts), '');
        $pending = [];
        foreach ($texts as $index => $text) {
            $text = trim($text);
            if ($text === '' || $this->languageDetector->shouldSkipTranslation($text, $to)) {
                $results[$index] = $text;

                continue;
            }

            $pending[$index] = $text;
        }

        if ($pending === []) {
            return $results;
        }

        if (count($pending) === 1) {
            $only = array_key_first($pending);
            $results[$only] = $this->translate($pending[$only], $from, $to);

            return $results;
        }

        $values = array_values($pending);
        $batch = $this->translateBatch($values, $from, $to);

        foreach (array_keys($pending) as $position => $index) {
            $translated = $batch[$position] ?? '';
            $results[$index] = $translated !== ''
                ? $translated
                : $this->translate($pending[$index], $from, $to);
        }

        return $results;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function translateBatch(array $values, string $from, string $to): array
    {
        $payload = json_encode(array_values($values), JSON_UNESCAPED_UNICODE) ?: '[]';

        for ($attempt = 0; $attempt < self::BATCH_ATTEMPTS; $attempt++) {
            $temperature = $attempt === 0 ? 0.2 : 0.1;
            $response = $this->client->complete(
                $this->systemPrompt($from, $to, true),
                $payload,
                8000,
                $temperature,
            );
            $parsed = $this->parseJsonArray($response, count($values));
            if ($parsed !== null) {
                return array_map(
                    fn (string $translation, string $source): string => $this->finalizeTranslation($translation, $source),
                    $parsed,
                    $values,
                );
            }
        }

        return array_fill(0, count($values), '');
    }

    private function finalizeTranslation(string $translation, string $source): string
    {
        $sanitized = $this->sanitizer->sanitize($translation, $source);

        return $sanitized !== '' ? $sanitized : $source;
    }

    /**
     * @return list<string>|null
     */
    private function parseJsonArray(string $response, int $expected): ?array
    {
        $clean = trim($response);
        $start = strpos($clean, '[');
        $end = strrpos($clean, ']');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);
        if (! is_array($decoded) || count($decoded) !== $expected) {
            return null;
        }

        return array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            array_values($decoded),
        );
    }

    private function systemPrompt(string $from, string $to, bool $batch): string
    {
        $prompt = 'You are a professional technical translator. Translate from '.$from.' to '.$to.'. '
            .'Preserve technical meaning, numbers, units and inline punctuation. '
            .'If a segment is already in '.$to.', return it unchanged. '
            .'Output ONLY the translation. Never add dialogue labels such as "User:", "Answer:", "Пользователь:", or "Ответ:". '
            .'Do not add commentary, examples, or extra sentences.';

        $glossary = $this->glossary->promptFragment($from, $to);
        if ($glossary !== '') {
            $prompt .= ' '.$glossary;
        }

        $prompt .= $batch
            ? ' Input is a JSON array of strings. Return ONLY a JSON array of strings of the same length and order, '
                .'where each element is the translation of the corresponding input. No code fences, no extra text.'
            : ' Return only the translation without commentary.';

        return $prompt;
    }
}
