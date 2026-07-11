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
use Illuminate\Support\Facades\DB;

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
        DB::transaction(function () use ($document): void {
            $documentId = $document->id()->value;

            $model = DocumentModel::query()->find($documentId);
            if ($model === null) {
                throw DocumentNotFound::withId($document->id());
            }

            $model->update($this->documents->toModelAttributes($document));

            $domainBlocks = $document->blocks();
            $existingIds = DocumentBlockModel::query()
                ->where('document_id', $documentId)
                ->pluck('id')
                ->all();

            if ($domainBlocks === []) {
                if ($existingIds !== []) {
                    DocumentBlockModel::query()
                        ->where('document_id', $documentId)
                        ->delete();
                }

                return;
            }

            $incomingIds = [];
            foreach ($domainBlocks as $block) {
                $incomingIds[] = $block->id;
                $attributes = $this->blocks->toModelAttributes($block, $documentId);

                DocumentBlockModel::query()->updateOrCreate(
                    ['id' => $block->id],
                    $attributes,
                );
            }

            $idsToDelete = array_diff($existingIds, $incomingIds);
            if ($idsToDelete !== []) {
                DocumentBlockModel::query()
                    ->where('document_id', $documentId)
                    ->whereIn('id', $idsToDelete)
                    ->delete();
            }
        });
    }

    public function delete(DocumentId $id): void
    {
        $deleted = DocumentModel::query()->whereKey($id->value)->delete();
        if ($deleted === 0) {
            throw DocumentNotFound::withId($id);
        }
    }
}
