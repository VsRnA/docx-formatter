<?php

namespace App\Services\Ai\Support;

/**
 * Strips dialogue-style LLM artefacts and rejects obvious hallucinations
 * on short/ambiguous segments (e.g. "CONTENTS" → pipeline example).
 */
final class TranslationResponseSanitizer
{
    public function sanitize(string $response, string $source): string
    {
        $raw = trim($response);
        $hadDialogue = preg_match('/(?:Пользователь|User)\s*:/u', $raw) === 1;
        $clean = $this->stripDialogueWrappers($raw);

        if ($clean === '') {
            return '';
        }

        if ($hadDialogue && $this->isShortSource($source)) {
            return '';
        }

        if ($this->isLikelyHallucination($clean, $source)) {
            return '';
        }

        return $clean;
    }

    private function isShortSource(string $source): bool
    {
        $words = preg_split('/\s+/u', trim($source)) ?: [];

        return count(array_filter($words, static fn (string $word): bool => $word !== '')) <= 3;
    }

    public function stripDialogueWrappers(string $text): string
    {
        if ($text === '') {
            return '';
        }

        if (preg_match('/(?:Пользователь|User)\s*:.+?(?:Ответ|Answer|Translation|Перевод)\s*:\s*(.+)$/su', $text, $match) === 1) {
            return trim($match[1]);
        }

        if (preg_match('/^(?:Пользователь|User|Ответ|Answer|Assistant|Перевод|Translation)\s*:\s*(.+)$/su', $text, $match) === 1) {
            $candidate = trim($match[1]);
            if (preg_match('/^(?:Ответ|Answer|Translation|Перевод)\s*:/u', $candidate) === 1) {
                return $this->stripDialogueWrappers($candidate);
            }

            return $candidate;
        }

        return $text;
    }

    public function isLikelyHallucination(string $translated, string $source): bool
    {
        $source = trim($source);
        $translated = trim($translated);

        if ($translated === '') {
            return true;
        }

        if (preg_match('/(?:Пользователь|User)\s*:/u', $translated) === 1) {
            return true;
        }

        $sourceWords = preg_split('/\s+/u', $source) ?: [];
        $wordCount = count(array_filter($sourceWords, static fn (string $word): bool => $word !== ''));

        if ($wordCount <= 3 && mb_strlen($translated) > max(40, mb_strlen($source) * 4)) {
            if (substr_count($translated, '.') >= 2) {
                return true;
            }

            if (preg_match('/(?:Ответ|Answer|Translation|Перевод)\s*:/u', $translated) === 1) {
                return true;
            }
        }

        return false;
    }
}
