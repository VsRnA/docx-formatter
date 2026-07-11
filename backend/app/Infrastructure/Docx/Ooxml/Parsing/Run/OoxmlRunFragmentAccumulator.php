<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Run;

use App\Domain\Docx\Service\Support\TextRunFragmentMerger;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMElement;

/**
 * Merges sequential w:r fragments with safe deduplication rules.
 */
final class OoxmlRunFragmentAccumulator
{
    /** @var list<string> */
    private array $parts = [];

    private string $accumulatedPlain = '';

    private string $lastPlainPart = '';

    private ?DOMElement $lastRun = null;

    public function __construct(
        private readonly TextRunFragmentMerger $merger,
        private readonly OoxmlRunTextFormatter $textFormatter,
    ) {}

    /**
     * @param  array{html: string, plain: string, inline: array<string, mixed>, run: DOMElement}  $parsed
     * @param  array<string, mixed>  $inline
     */
    public function appendRunFragment(
        array $parsed,
        ?string $paragraphStyleId,
        array &$inline,
    ): void {
        if ($parsed['plain'] === '' && $parsed['html'] === '') {
            return;
        }

        if ($parsed['plain'] === '' && $parsed['html'] !== '') {
            if (! $this->hasEquivalentPart($parsed['html'])) {
                $this->parts[] = $parsed['html'];
            }

            return;
        }

        $plainPart = $parsed['plain'];

        if ($this->merger->repeatsAccumulated($this->accumulatedPlain, $plainPart)) {
            $lastTextIndex = $this->lastTextPartIndex();
            if ($lastTextIndex !== null) {
                $this->parts[$lastTextIndex] = $this->merger->prefersRicherHtml(
                    (string) $this->parts[$lastTextIndex],
                    $parsed['html'],
                );
            } elseif (! $this->isIgnorableRepeatedPlain($plainPart)) {
                $this->parts[] = $parsed['html'];
            }

            $this->accumulatedPlain = $plainPart;
            $this->lastPlainPart = $plainPart;
            $this->lastRun = $parsed['run'];

            return;
        }

        if ($this->merger->repeatsPreviousExact($this->lastPlainPart, $plainPart)
            && $this->sameRunFormatting($this->lastRun, $parsed['run'])) {
            $lastIndex = array_key_last($this->parts);
            $this->parts[$lastIndex] = $this->merger->prefersRicherHtml(
                (string) ($this->parts[$lastIndex] ?? ''),
                $parsed['html'],
            );
            $this->lastPlainPart = $plainPart;
            $this->lastRun = $parsed['run'];

            return;
        }

        $suffixPlain = $this->merger->nonOverlappingSuffix($this->accumulatedPlain, $plainPart);
        if ($suffixPlain === '') {
            if ($this->merger->repeatsPreviousExact($this->accumulatedPlain, $plainPart)) {
                return;
            }

            $suffixPlain = $plainPart;
        }

        $this->parts[] = $suffixPlain === $plainPart
            ? $parsed['html']
            : $this->textFormatter->formatPlainWithRun(
                $suffixPlain,
                $parsed['run'],
                $paragraphStyleId,
                $inline,
            );

        $this->accumulatedPlain .= $suffixPlain;
        $this->lastPlainPart = $suffixPlain;
        $this->lastRun = $parsed['run'];
    }

    public function appendHtml(string $html): void
    {
        if ($html !== '' && ! $this->hasEquivalentPart($html)) {
            $this->parts[] = $html;
        }
    }

    /** @return list<string> */
    public function parts(): array
    {
        return $this->parts;
    }

    private function hasEquivalentPart(string $html): bool
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($html)) ?? trim($html);

        foreach ($this->parts as $part) {
            if ((preg_replace('/\s+/u', ' ', trim($part)) ?? trim($part)) === $normalized) {
                return true;
            }
        }

        return false;
    }

    private function sameRunFormatting(?DOMElement $left, ?DOMElement $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        return OoxmlXml::serializeRunProperties(OoxmlXml::child($left, 'rPr'))
            === OoxmlXml::serializeRunProperties(OoxmlXml::child($right, 'rPr'));
    }

    private function lastTextPartIndex(): ?int
    {
        for ($index = count($this->parts) - 1; $index >= 0; $index--) {
            if ($this->isNonTextFragment($this->parts[$index])) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function isNonTextFragment(string $html): bool
    {
        return str_contains($html, '<figure')
            || str_contains($html, 'doc-symbol-row')
            || str_contains($html, 'doc-anchor-shape');
    }

    private function isIgnorableRepeatedPlain(string $plain): bool
    {
        return trim($plain) === '';
    }
}
