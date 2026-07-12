<?php

namespace App\Domain\Document\Entity;

use App\Domain\Document\Exception\BlockNotFound;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentMeta;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Document\ValueObject\ParseCoverage;
use App\Domain\Document\ValueObject\ProcessingStage;

final class Document
{
    /** @param  list<DocumentBlock>  $blocks */
    public function __construct(
        private readonly DocumentId $id,
        private string $title,
        private ?string $slug,
        private ?string $sourceFileKey,
        private string $languageFrom,
        private string $languageTo,
        private DocumentStatus $status,
        private ?string $processingStage,
        private ?string $processingError,
        private ?string $htmlDraft,
        private ?string $htmlPublished,
        private DocumentMeta $meta,
        private array $blocks = [],
    ) {}

    public function id(): DocumentId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): ?string
    {
        return $this->slug;
    }

    public function sourceFileKey(): ?string
    {
        return $this->sourceFileKey;
    }

    public function languageFrom(): string
    {
        return $this->languageFrom;
    }

    public function languageTo(): string
    {
        return $this->languageTo;
    }

    public function status(): DocumentStatus
    {
        return $this->status;
    }

    public function processingStage(): ?string
    {
        return $this->processingStage;
    }

    public function processingError(): ?string
    {
        return $this->processingError;
    }

    public function htmlDraft(): ?string
    {
        return $this->htmlDraft;
    }

    public function htmlPublished(): ?string
    {
        return $this->htmlPublished;
    }

    public function meta(): DocumentMeta
    {
        return $this->meta;
    }

    /** @return list<DocumentBlock> */
    public function blocks(): array
    {
        return $this->blocks;
    }

    public function shouldTranslate(): bool
    {
        return $this->meta->shouldTranslate();
    }

    public function markProcessing(ProcessingStage $stage): void
    {
        $this->status = DocumentStatus::Processing;
        $this->processingStage = $stage->value;
        $this->processingError = null;
    }

    public function markFailed(string $error): void
    {
        $this->status = DocumentStatus::Failed;
        $this->processingStage = ProcessingStage::failed()->value;
        $this->processingError = $error;
    }

    public function markReady(string $htmlDraft): void
    {
        $this->status = DocumentStatus::Ready;
        $this->processingStage = ProcessingStage::completed()->value;
        $this->processingError = null;
        $this->htmlDraft = $htmlDraft;
    }

    /**
     * @param  list<array<string, mixed>>  $warnings
     * @param  array<string, mixed>  $parseMeta
     */
    public function recordParseResult(ParseCoverage $coverage, array $warnings, array $parseMeta): void
    {
        $this->meta = $this->meta->merge([
            'parse_warnings' => $warnings,
            'parse_meta' => $parseMeta,
            'parse_coverage' => $coverage->toArray(),
        ]);
    }

    public function recordNormalization(int $normalized, int $skipped): void
    {
        $this->meta = $this->meta->merge([
            'ai_normalize' => [
                'normalized' => $normalized,
                'skipped' => $skipped,
            ],
        ]);
    }

    /** @param  array<string, mixed>  $patch */
    public function mergeMeta(array $patch): void
    {
        $this->meta = $this->meta->merge($patch);
    }

    /** @param  list<DocumentBlock>  $blocks */
    public function replaceBlocks(array $blocks): void
    {
        $this->blocks = $blocks;
    }

    public function addBlock(DocumentBlock $block): void
    {
        $this->blocks[] = $block;
    }

    public function updateBlock(string $blockId, DocumentBlock $updated): void
    {
        foreach ($this->blocks as $index => $block) {
            if ($block->id === $blockId) {
                $this->blocks[$index] = $updated;

                return;
            }
        }

        throw BlockNotFound::withId($blockId);
    }

    public function removeBlock(string $blockId): void
    {
        $filtered = array_values(array_filter(
            $this->blocks,
            fn (DocumentBlock $block) => $block->id !== $blockId,
        ));

        if (count($filtered) === count($this->blocks)) {
            throw BlockNotFound::withId($blockId);
        }

        $this->blocks = $filtered;
    }

    /** @param  list<string>  $orderedIds */
    public function reorderBlocks(array $orderedIds): void
    {
        $byId = [];
        foreach ($this->blocks as $block) {
            $byId[$block->id] = $block;
        }

        $reordered = [];
        foreach ($orderedIds as $sort => $blockId) {
            if (! isset($byId[$blockId])) {
                continue;
            }

            $reordered[] = $byId[$blockId]->withSort((int) $sort);
        }

        $this->blocks = $reordered;
    }

    public function maxBlockSort(): int
    {
        $max = 0;
        foreach ($this->blocks as $block) {
            $max = max($max, $block->sort);
        }

        return $max;
    }

    public function hasBlock(string $blockId): bool
    {
        foreach ($this->blocks as $block) {
            if ($block->id === $blockId) {
                return true;
            }
        }

        return false;
    }

    public function findBlock(string $blockId): DocumentBlock
    {
        foreach ($this->blocks as $block) {
            if ($block->id === $blockId) {
                return $block;
            }
        }

        throw BlockNotFound::withId($blockId);
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function setHtmlPublished(?string $html): void
    {
        $this->htmlPublished = $html;
    }

    public function setHtmlDraft(?string $html): void
    {
        $this->htmlDraft = $html;
    }

    public function setStatus(DocumentStatus $status): void
    {
        $this->status = $status;
    }
}
