<?php

namespace App\Application\Document\Command\DeleteDocument;

use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Shared\Port\FileStoragePort;
use App\Models\Document as DocumentModel;

final class DeleteDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly FileStoragePort $storage,
    ) {}

    public function execute(string $documentId): void
    {
        $model = DocumentModel::query()->with('resources')->findOrFail($documentId);

        $keys = array_filter([
            $model->source_file_key,
            is_array($model->meta_json) ? ($model->meta_json['translated_file_key'] ?? null) : null,
        ]);

        foreach ($model->resources as $resource) {
            if ($resource->storage_key !== '') {
                $keys[] = $resource->storage_key;
            }
        }

        foreach (array_unique($keys) as $key) {
            if (is_string($key) && $key !== '' && $this->storage->exists($key)) {
                $this->storage->delete($key);
            }
        }

        $this->documents->delete(new DocumentId($documentId));
    }
}
