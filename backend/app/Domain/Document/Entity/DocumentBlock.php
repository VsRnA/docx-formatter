<?php

namespace App\Domain\Document\Entity;

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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'sort' => $this->sort,
            'html' => $this->html,
            'text_original' => $this->textOriginal,
            'text_translated' => $this->textTranslated,
            'translation_status' => $this->translationStatus->value,
            'styles' => $this->styles,
            'meta' => $this->meta,
            'assets' => $this->assets,
            'content_json' => $this->contentJson,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            type: BlockType::from((string) $data['type']),
            sort: (int) ($data['sort'] ?? 0),
            html: isset($data['html']) ? (string) $data['html'] : null,
            textOriginal: isset($data['text_original']) ? (string) $data['text_original'] : null,
            textTranslated: isset($data['text_translated']) ? (string) $data['text_translated'] : null,
            translationStatus: TranslationStatus::from((string) ($data['translation_status'] ?? 'skipped')),
            styles: is_array($data['styles'] ?? null) ? $data['styles'] : null,
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : null,
            assets: is_array($data['assets'] ?? null) ? $data['assets'] : null,
            contentJson: is_array($data['content_json'] ?? null) ? $data['content_json'] : null,
        );
    }
}
