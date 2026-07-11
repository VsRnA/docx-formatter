<?php

namespace App\Infrastructure\Document\Persist;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\Entity\ParsedDocument;
use App\Enums\BlockType as EloquentBlockType;
use App\Infrastructure\Document\Translation\DocumentTranslationBatchTranslator;
use App\Infrastructure\Document\Translation\DocumentTranslationCollector;
use App\Infrastructure\Docx\Ooxml\Ir\BlockContentIrBuilder;
use App\Models\Document;
use App\Models\DocumentBlock;

final class ParsedBlockPersister
{
    public function __construct(
        private readonly BlockImageAssetService $images,
        private readonly BlockTranslationApplicator $translation,
        private readonly BlockContentIrBuilder $irBuilder,
        private readonly DocumentTranslationCollector $collector,
        private readonly DocumentTranslationBatchTranslator $batchTranslator,
    ) {}

    public function persistAll(Document $document, ParsedDocument $parsed, bool $translate): void
    {
        DocumentBlock::query()->where('document_id', $document->id)->delete();

        $translationsByText = $translate
            ? $this->batchTranslator->translateAll($document, $this->collector->collect($parsed))
            : [];

        foreach ($parsed->blocks as $block) {
            $this->persistOne($document, $block, $translate, $translationsByText);
        }
    }

    /**
     * @param  array<string, string>  $translationsByText
     */
    private function persistOne(
        Document $document,
        ParsedBlock $block,
        bool $translate,
        array $translationsByText,
    ): void {
        $pendingImages = $block->meta['pending_images'] ?? null;
        if (is_array($pendingImages) && $pendingImages !== []) {
            $resolved = $this->images->resolvePendingImages($document, (string) $block->html, $pendingImages);
            $html = $resolved['html'];
            $assets = $resolved['assets'];
        } else {
            $image = $this->images->uploadIfNeeded($document, $block);
            $html = $image['html'];
            $assets = $image['assets'];
        }

        $translated = $this->translation->apply($document, $block, $html, $translate, $translationsByText);
        $meta = array_merge($block->meta ?? [], ['parse' => true], $translated['meta'] ?? []);
        unset($meta['pending_images']);

        $document->blocks()->create([
            'type' => EloquentBlockType::from($block->type->value),
            'sort' => $block->sort,
            'html' => $translated['html'],
            'content_json' => $this->irBuilder->fromParsedBlock($block),
            'text_original' => $block->textOriginal,
            'text_translated' => $translated['text_translated'],
            'translation_status' => $translated['translation_status'],
            'styles_json' => $block->styles,
            'meta_json' => $meta,
            'assets_json' => $assets,
        ]);
    }
}
