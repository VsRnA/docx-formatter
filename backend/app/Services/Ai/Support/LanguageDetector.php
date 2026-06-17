<?php

namespace App\Services\Ai\Support;

/**
 * Lightweight script-based language guess. Good enough to skip segments that
 * are already in the target language (e.g. RU text in an EN->RU job), which is
 * the main source of duplicated RU+EN output.
 */
final class LanguageDetector
{
    private const DOMINANT_RATIO = 0.6;

    public function isLikely(string $text, string $lang): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $text) ?? '';
        $total = mb_strlen($letters);
        if ($total === 0) {
            return false;
        }

        return match (strtolower($lang)) {
            'ru' => $this->ratio('/\p{Cyrillic}/u', $letters) >= self::DOMINANT_RATIO,
            'en' => $this->ratio('/[A-Za-z]/u', $letters) >= self::DOMINANT_RATIO,
            default => false,
        };
    }

    /**
     * Whether translation can be skipped: no translatable letters at all
     * (numbers/punctuation) or the text is already in the target language.
     */
    public function shouldSkipTranslation(string $text, string $targetLang): bool
    {
        if (preg_match('/\p{L}/u', $text) !== 1) {
            return true;
        }

        return $this->isLikely($text, $targetLang);
    }

    private function ratio(string $pattern, string $letters): float
    {
        $matches = preg_match_all($pattern, $letters);
        $total = mb_strlen($letters);

        return $total > 0 ? $matches / $total : 0.0;
    }
}
