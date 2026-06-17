<?php

namespace App\Infrastructure\Document\Translation;

use App\Domain\Docx\Port\TranslatorPort;
use App\Enums\BlockType;
use App\Infrastructure\Document\BlockHtmlWrapper;
use App\Models\Document;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlHtmlSegmentAnnotator;

final class SegmentTranslationCoordinator
{
    public function __construct(
        private readonly TranslatorPort $translator,
        private readonly OoxmlHtmlSegmentAnnotator $segmentHtml,
        private readonly TranslatedHtmlPatcher $htmlPatcher,
    ) {}

    /**
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     * @return array{0: array<int, string>, 1: list<string>}
     */
    public function translate(Document $document, array $segments): array
    {
        $segmentIds = [];
        $sourceTexts = [];

        foreach ($segments as $segment) {
            if (! ($segment['translatable'] ?? true)) {
                continue;
            }

            $text = trim((string) ($segment['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $segmentIds[] = (int) $segment['id'];
            $sourceTexts[] = $text;
        }

        if ($sourceTexts === []) {
            return [[], []];
        }

        $translatedTexts = $this->translator->translateMany(
            $sourceTexts,
            $document->language_from,
            $document->language_to,
        );

        $translations = [];
        $translatedParts = [];
        foreach ($segmentIds as $position => $segmentId) {
            $translated = trim((string) ($translatedTexts[$position] ?? ''));
            if ($translated === '') {
                $translated = $sourceTexts[$position];
            }

            $translations[$segmentId] = $translated;
            $translatedParts[] = $translated;
        }

        return [$translations, $translatedParts];
    }

    /**
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     * @param  array<int, string>  $translations
     */
    public function applyToHtml(string $html, array $segments, array $translations, BlockType $type): string
    {
        $html = BlockHtmlWrapper::stripUnsupportedFigures($html);
        $html = $this->applySegmentTranslations($html, $segments, $translations);

        if ($this->segmentHtml->hasUntranslatedSegments($html, $segments, $translations)) {
            $html = $this->segmentHtml->annotate($html, $segments);
            $html = $this->applySegmentTranslations($html, $segments, $translations);
        }

        if ($this->segmentHtml->hasUntranslatedSegments($html, $segments, $translations)) {
            $combinedTranslation = trim(implode("\n", $this->translatedParts($segments, $translations)));
            if ($combinedTranslation !== '') {
                $html = $this->htmlPatcher->apply($html, $combinedTranslation, $type);
            }
        }

        return $html;
    }

    /**
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     * @param  array<int, string>  $translations
     */
    private function applySegmentTranslations(string $html, array $segments, array $translations): string
    {
        $html = $this->segmentHtml->applyTranslations($html, $translations, $segments);

        foreach ($segments as $segment) {
            if (! ($segment['translatable'] ?? true)) {
                continue;
            }

            $id = (int) ($segment['id'] ?? -1);
            if (! isset($translations[$id])) {
                continue;
            }

            $original = (string) ($segment['text'] ?? '');
            $translated = $translations[$id];
            if ($original === '' || $original === $translated) {
                continue;
            }

            if ($this->segmentHtml->visibleTextContainsBoth($html, $original, $translated)) {
                $html = $this->segmentHtml->removeUntaggedCopies($html, $original);
            }
        }

        return $html;
    }

    /**
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     * @param  array<int, string>  $translations
     * @return list<string>
     */
    private function translatedParts(array $segments, array $translations): array
    {
        $translatedParts = [];
        foreach ($segments as $segment) {
            if (! ($segment['translatable'] ?? true)) {
                continue;
            }

            $id = (int) ($segment['id'] ?? -1);
            if (! isset($translations[$id])) {
                continue;
            }

            $translated = trim((string) $translations[$id]);
            if ($translated !== '') {
                $translatedParts[] = $translated;
            }
        }

        return $translatedParts;
    }

    /**
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     */
    public function originalText(array $segments): string
    {
        $parts = [];
        foreach ($segments as $segment) {
            if ($segment['translatable'] ?? true) {
                $parts[] = (string) $segment['text'];
            }
        }

        return implode("\n", $parts);
    }
}
