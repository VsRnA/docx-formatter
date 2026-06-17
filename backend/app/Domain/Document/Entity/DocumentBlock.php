<?php

namespace App\Domain\Document\Entity;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentMeta;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Document\ValueObject\ParseCoverage;
use App\Domain\Document\ValueObject\ProcessingStage;
use App\Domain\Document\ValueObject\TranslationStatus;
use App\Domain\Docx\ValueObject\BlockType;

final class DocumentBlock
{
    /**
     * @param  array<string, mixed>|null  $styles
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, mixed>|null  $assets
     * @param  array<string, mixed>|null  $contentJson
     */
    public function __construct(
        public readonly string $id,
        public BlockType $type,
        public int $sort,
        public ?string $html,
        public ?string $textOriginal,
        public ?string $textTranslated,
        public TranslationStatus $translationStatus,
        public ?array $styles = null,
        public ?array $meta = null,
        public ?array $assets = null,
        public ?array $contentJson = null,
    ) {}

    public function withSort(int $sort): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            sort: $sort,
            html: $this->html,
            textOriginal: $this->textOriginal,
            textTranslated: $this->textTranslated,
            translationStatus: $this->translationStatus,
            styles: $this->styles,
            meta: $this->meta,
            assets: $this->assets,
            contentJson: $this->contentJson,
        );
    }

    /**
     * @param  array<string, mixed>|null  $styles
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, mixed>|null  $assets
     */
    public function withContent(
        ?string $html = null,
        ?array $styles = null,
        ?array $meta = null,
        ?array $assets = null,
        ?int $sort = null,
    ): self {
        return new self(
            id: $this->id,
            type: $this->type,
            sort: $sort ?? $this->sort,
            html: $html ?? $this->html,
            textOriginal: $this->textOriginal,
            textTranslated: $this->textTranslated,
            translationStatus: $this->translationStatus,
            styles: $styles ?? $this->styles,
            meta: $meta ?? $this->meta,
            assets: $assets ?? $this->assets,
            contentJson: $this->contentJson,
        );
    }
}
