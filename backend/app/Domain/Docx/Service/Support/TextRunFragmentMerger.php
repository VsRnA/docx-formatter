<?php

namespace App\Domain\Docx\Service\Support;

/**
 * Merges duplicate/overlapping w:r fragments (plain + bold runs, mid-token splits).
 */
final class TextRunFragmentMerger
{
    public function normalize(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public function repeatsAccumulated(string $accumulatedPlain, string $newPlain): bool
    {
        return $newPlain !== '' && $accumulatedPlain !== '' && $newPlain === $accumulatedPlain;
    }

    public function repeatsPrevious(string $previousPlain, string $newPlain): bool
    {
        $previous = $this->normalize($previousPlain);
        $new = $this->normalize($newPlain);

        return $new !== '' && $previous !== '' && $new === $previous;
    }

    public function repeatsPreviousExact(string $previousPlain, string $newPlain): bool
    {
        return $newPlain !== '' && $previousPlain !== '' && $previousPlain === $newPlain;
    }

    public function isAlreadyContained(string $accumulatedPlain, string $newPlain): bool
    {
        $accumulated = $this->normalize($accumulatedPlain);
        $new = $this->normalize($newPlain);

        if ($new === '' || $accumulated === '') {
            return false;
        }

        return $accumulated === $new || str_ends_with($accumulated, $new);
    }

    public function isSuperset(string $accumulatedPlain, string $newPlain): bool
    {
        $accumulated = $this->normalize($accumulatedPlain);
        $new = $this->normalize($newPlain);

        return $accumulated !== ''
            && $new !== ''
            && str_starts_with($new, $accumulated)
            && $new !== $accumulated;
    }

    public function dedupeDoubledText(string $text): string
    {
        $text = $this->normalize($text);
        $length = mb_strlen($text);

        // Only collapse substantial, exact phrase duplications. Short tokens and
        // bare numbers (e.g. "okok", "20242024") are legitimate content.
        if ($length < 6 || $length % 2 !== 0) {
            return $text;
        }

        $half = (int) ($length / 2);
        $left = mb_substr($text, 0, $half);
        $right = mb_substr($text, $half);

        if ($left !== $right || preg_match('/^\d+$/u', $left) === 1) {
            return $text;
        }

        return $left;
    }

    public function prefersRicherHtml(string $existingHtml, string $newHtml): string
    {
        return $this->formattingScore($newHtml) >= $this->formattingScore($existingHtml)
            ? $newHtml
            : $existingHtml;
    }

    public function formattingScore(string $html): int
    {
        return substr_count($html, '<strong>')
            + substr_count($html, '<em>')
            + substr_count($html, '<u>')
            + substr_count($html, '<span');
    }

    public function nonOverlappingSuffix(string $accumulatedPlain, string $newPlain): string
    {
        if ($accumulatedPlain === '' || $newPlain === '') {
            return $newPlain;
        }

        // Require a substantial overlap (>= 8 chars) so that short coincidental
        // overlaps between legitimately distinct runs are not trimmed away.
        $max = min(mb_strlen($accumulatedPlain), mb_strlen($newPlain));
        for ($size = $max; $size >= 8; $size--) {
            $left = mb_substr($accumulatedPlain, -$size);
            $right = mb_substr($newPlain, 0, $size);
            if ($left === $right) {
                return mb_substr($newPlain, $size);
            }
        }

        return $newPlain;
    }
}
