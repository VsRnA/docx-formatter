<?php

namespace App\Infrastructure\External\Ai\Support;

/**
 * Configurable terminology map injected into the system prompt so that
 * recurring technical terms translate consistently across segments.
 */
final class TranslationGlossary
{
    public function promptFragment(string $from, string $to): string
    {
        $terms = config('services.translation.glossary', []);
        if (! is_array($terms) || $terms === []) {
            return '';
        }

        $pairs = [];
        foreach ($terms as $source => $target) {
            if (is_string($source) && is_string($target) && trim($source) !== '' && trim($target) !== '') {
                $pairs[] = '"'.$source.'" -> "'.$target.'"';
            }
        }

        if ($pairs === []) {
            return '';
        }

        return 'Use this glossary for consistent terminology ('.$from.' to '.$to.'): '.implode('; ', $pairs).'.';
    }
}
