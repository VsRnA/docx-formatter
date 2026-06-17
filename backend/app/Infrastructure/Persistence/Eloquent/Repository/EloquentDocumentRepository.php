<?php

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Exception\DocumentNotFound;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Infrastructure\Persistence\Eloquent\Mapper\BlockMapper;
use App\Infrastructure\Persistence\Eloquent\Mapper\DocumentMapper;
use App\Models\Document as DocumentModel;
use App\Models\DocumentBlock as DocumentBlockModel;

final class EloquentDocumentRepository implements DocumentRepositoryInterface
{
    public function __construct(
        private readonly DocumentMapper $documents,
        private readonly BlockMapper $blocks,
    ) {}

    public function find(DocumentId $id): Document
    {
        $model = DocumentModel::query()->with('blocks')->find($id->value);
        if ($model === null) {
            throw DocumentNotFound::withId($id);
        }

        return $this->documents->toDomain($model);
    }

    public function insert(Document $document): void
    {
        $attributes = $this->documents->toModelAttributes($document);
        $model = new DocumentModel($attributes);
        $model->id = $document->id()->value;
        $model->save();
    }

    public function save(Document $document): void
    {
        $model = DocumentModel::query()->find($document->id()->value);
        if ($model === null) {
            throw DocumentNotFound::withId($document->id());
        }

        $model->update($this->documents->toModelAttributes($document));

        $domainBlocks = $document->blocks();
        if ($domainBlocks !== []) {
            DocumentBlockModel::query()
                ->where('document_id', $document->id()->value)
                ->delete();

            foreach ($domainBlocks as $block) {
                DocumentBlockModel::query()->create(
                    $this->blocks->toModelAttributes($block, $document->id()->value),
                );
            }
        }
    }
}
