<?php

namespace App\Infrastructure\Persistence\Eloquent\Query;

use App\Domain\Document\Exception\DocumentNotFound;
use App\Domain\Document\Query\DocumentEditorQueryPort;
use App\Domain\Document\Query\DocumentEditorReadModel;
use App\Domain\Document\ValueObject\DocumentId;
use App\Models\Document as DocumentModel;

final class EloquentDocumentEditorQuery implements DocumentEditorQueryPort
{
    public function load(DocumentId $id): DocumentEditorReadModel
    {
        $document = DocumentModel::query()
            ->with(['blocks', 'resources'])
            ->find($id->value);

        if ($document === null) {
            throw DocumentNotFound::withId($id);
        }

        return new DocumentEditorReadModel(
            document: $document,
            blocks: $document->blocks,
            resources: $document->resources,
        );
    }
}
