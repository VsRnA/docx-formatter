<?php

namespace App\Infrastructure\Persistence\Eloquent\Mapper;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentMeta;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Models\Document as DocumentModel;

final class DocumentMapper
{
    public function __construct(
        private readonly BlockMapper $blocks,
    ) {}

    public function toDomain(DocumentModel $model, bool $withBlocks = true): Document
    {
        $domainBlocks = [];
        if ($withBlocks) {
            $model->loadMissing('blocks');
            foreach ($model->blocks as $block) {
                $domainBlocks[] = $this->blocks->toDomain($block);
            }
        }

        return new Document(
            id: new DocumentId((string) $model->id),
            title: (string) $model->title,
            slug: $model->slug,
            sourceFileKey: $model->source_file_key,
            languageFrom: (string) $model->language_from,
            languageTo: (string) $model->language_to,
            status: DocumentStatus::from($model->status->value),
            processingStage: $model->processing_stage,
            processingError: $model->processing_error,
            htmlDraft: $model->html_draft,
            htmlPublished: $model->html_published,
            meta: new DocumentMeta($model->meta_json ?? []),
            blocks: $domainBlocks,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toModelAttributes(Document $document): array
    {
        return [
            'title' => $document->title(),
            'slug' => $document->slug(),
            'source_file_key' => $document->sourceFileKey(),
            'language_from' => $document->languageFrom(),
            'language_to' => $document->languageTo(),
            'status' => $document->status()->value,
            'processing_stage' => $document->processingStage(),
            'processing_error' => $document->processingError(),
            'html_draft' => $document->htmlDraft(),
            'html_published' => $document->htmlPublished(),
            'meta_json' => $document->meta()->toArray(),
        ];
    }
}
