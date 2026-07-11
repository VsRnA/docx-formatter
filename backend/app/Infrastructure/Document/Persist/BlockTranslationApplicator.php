<?php

namespace App\Infrastructure\Document\Persist;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Enums\BlockType;
use App\Enums\TranslationStatus;
use App\Infrastructure\Document\Translation\SegmentTranslationCoordinator;
use App\Infrastructure\Document\Translation\TranslatedHtmlPatcher;
use App\Infrastructure\Document\Translation\TranslationCacheStore;
use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Throwable;

class BlockTranslationApplicator
{
    public function __construct(
        private readonly SegmentTranslationCoordinator $segments,
        private readonly TranslatedHtmlPatcher $htmlPatcher,
    ) {}

    /**
     * @param  array<string, string>  $translationsByText
     * @return array{
     *     html: ?string,
     *     text_translated: ?string,
     *     translation_status: TranslationStatus,
     *     meta?: array<string, mixed>
     * }
     */
    public function apply(
        Document $document,
        ParsedBlock $block,
        ?string $html,
        bool $translate,
        array $translationsByText = [],
    ): array {
        if (! $translate) {
            return $this->skipped($html);
        }

        $tableCells = $block->meta['ooxml_table_cells'] ?? null;
        if (is_array($tableCells) && $tableCells !== []) {
            return $this->applyTableSegments($document, $block, $html, $tableCells, $translationsByText);
        }

        $segmentList = $block->meta['ooxml_segments'] ?? null;
        if (is_array($segmentList) && $segmentList !== []) {
            return $this->applyParagraphSegments($document, $html, $segmentList, $translationsByText);
        }

        if (! $block->textOriginal) {
            return $this->skipped($html);
        }

        return $this->applyLegacy($document, $block, $html, $translationsByText);
    }

    public function applyTranslationToHtml(string $html, string $text, BlockType $type): string
    {
        return $this->htmlPatcher->apply($html, $text, $type);
    }

    /**
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     * @param  array<string, string>  $translationsByText
     * @return array{html: ?string, text_translated: ?string, translation_status: TranslationStatus, meta: array<string, mixed>}
     */
    private function applyParagraphSegments(
        Document $document,
        ?string $html,
        array $segments,
        array $translationsByText,
    ): array {
        try {
            [$translations, $translatedParts] = $this->segments->translateFromPrecomputed($segments, $translationsByText);

            return [
                'html' => $this->segments->applyToHtml($html ?? '', $segments, $translations, BlockType::Paragraph),
                'text_translated' => $translatedParts !== [] ? implode("\n", $translatedParts) : null,
                'translation_status' => TranslationStatus::Done,
                'meta' => ['ooxml_segment_translations' => $translations],
            ];
        } catch (Throwable $e) {
            Log::warning('Segment translation failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'html' => $html,
                'text_translated' => $this->segments->originalText($segments),
                'translation_status' => TranslationStatus::Failed,
            ];
        }
    }

    /**
     * @param  list<array{cell_index: int, segments: list<array{id: int, text: string, translatable?: bool}>}>  $tableCells
     * @param  array<string, string>  $translationsByText
     * @return array{html: ?string, text_translated: ?string, translation_status: TranslationStatus, meta?: array<string, mixed>}
     */
    private function applyTableSegments(
        Document $document,
        ParsedBlock $block,
        ?string $html,
        array $tableCells,
        array $translationsByText,
    ): array {
        $allSegments = [];
        foreach ($tableCells as $cell) {
            foreach ($cell['segments'] ?? [] as $segment) {
                $allSegments[] = $segment;
            }
        }

        if ($allSegments === []) {
            return $this->skipped($html);
        }

        try {
            [$translations, $translatedParts] = $this->segments->translateFromPrecomputed($allSegments, $translationsByText);

            return [
                'html' => $this->segments->applyToHtml($html ?? '', $allSegments, $translations, BlockType::Table),
                'text_translated' => $translatedParts !== [] ? implode("\n", $translatedParts) : null,
                'translation_status' => TranslationStatus::Done,
                'meta' => ['ooxml_segment_translations' => $translations],
            ];
        } catch (Throwable $e) {
            Log::warning('Table segment translation failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'html' => $html,
                'text_translated' => $block->textOriginal,
                'translation_status' => TranslationStatus::Failed,
            ];
        }
    }

    /**
     * @param  array<string, string>  $translationsByText
     * @return array{html: ?string, text_translated: ?string, translation_status: TranslationStatus}
     */
    private function applyLegacy(
        Document $document,
        ParsedBlock $block,
        ?string $html,
        array $translationsByText,
    ): array {
        try {
            $key = TranslationCacheStore::normalizeTextKey((string) $block->textOriginal);
            $translated = trim((string) ($translationsByText[$key] ?? ''));
            if ($translated === '') {
                $translated = (string) $block->textOriginal;
            }

            if ($translated && $block->type->value !== BlockType::Image->value && ! $this->htmlPatcher->shouldPreserveLayout($html ?? '')) {
                $html = $this->htmlPatcher->apply($html ?? '', $translated, BlockType::from($block->type->value));
            }

            return [
                'html' => $html,
                'text_translated' => $translated,
                'translation_status' => TranslationStatus::Done,
            ];
        } catch (Throwable $e) {
            Log::warning('Block translation failed', [
                'document_id' => $document->id,
                'block_type' => $block->type->value,
                'error' => $e->getMessage(),
            ]);

            return [
                'html' => $html,
                'text_translated' => $block->textOriginal,
                'translation_status' => TranslationStatus::Failed,
            ];
        }
    }

    /**
     * @return array{html: ?string, text_translated: null, translation_status: TranslationStatus}
     */
    private function skipped(?string $html): array
    {
        return [
            'html' => $html,
            'text_translated' => null,
            'translation_status' => TranslationStatus::Skipped,
        ];
    }
}
