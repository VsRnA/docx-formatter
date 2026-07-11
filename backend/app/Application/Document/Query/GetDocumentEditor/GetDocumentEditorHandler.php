<?php

namespace App\Application\Document\Query\GetDocumentEditor;

use App\Domain\Document\Query\DocumentEditorQueryPort;
use App\Domain\Document\Query\DocumentEditorReadModel;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Shared\Port\FileStoragePort;

final class GetDocumentEditorHandler
{
    public function __construct(
        private readonly DocumentEditorQueryPort $editorQuery,
        private readonly FileStoragePort $storage,
    ) {}

    public function execute(string $documentId): DocumentEditorReadModel
    {
        $readModel = $this->editorQuery->load(new DocumentId($documentId));

        $resources = $readModel->resources->map(function ($resource) {
            if ($resource->storage_key && ! $resource->url) {
                $resource->url = $this->storage->temporaryUrl($resource->storage_key);
            }

            return $resource;
        });

        return new DocumentEditorReadModel(
            document: $readModel->document,
            blocks: $readModel->blocks,
            resources: $resources,
        );
    }
}
