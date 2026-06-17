<?php

namespace App\Infrastructure\Persistence\Eloquent\Mapper;

use App\Domain\Document\Entity\DocumentBlock;
use App\Domain\Document\ValueObject\TranslationStatus;
use App\Domain\Docx\ValueObject\BlockType;
use App\Models\DocumentBlock as DocumentBlockModel;

final class BlockMapper
{
    public function toDomain(DocumentBlockModel $model): DocumentBlock
    {
        return new DocumentBlock(
            id: (string) $model->id,
            type: BlockType::from($model->type->value),
            sort: (int) $model->sort,
            html: $model->html,
            textOriginal: $model->text_original,
            textTranslated: $model->text_translated,
            translationStatus: TranslationStatus::from($model->translation_status->value),
            styles: $model->styles_json,
            meta: $model->meta_json,
            assets: $model->assets_json,
            contentJson: $model->content_json,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toModelAttributes(DocumentBlock $block, string $documentId): array
    {
        return [
            'id' => $block->id,
            'document_id' => $documentId,
            'type' => $block->type->value,
            'sort' => $block->sort,
            'html' => $block->html,
            'text_original' => $block->textOriginal,
            'text_translated' => $block->textTranslated,
            'translation_status' => $block->translationStatus->value,
            'styles_json' => $block->styles,
            'meta_json' => $block->meta,
            'assets_json' => $block->assets,
            'content_json' => $block->contentJson,
        ];
    }
}
