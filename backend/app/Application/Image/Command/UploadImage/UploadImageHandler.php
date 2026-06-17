<?php

namespace App\Application\Image\Command\UploadImage;

use App\Domain\Document\Repository\ResourceRepositoryInterface;
use App\Domain\Shared\Port\FileStoragePort;
use App\Enums\ResourceType;
use App\Models\Document as DocumentModel;
use App\Models\DocumentResource;
use Illuminate\Http\UploadedFile;

final class UploadImageHandler
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resources,
        private readonly FileStoragePort $storage,
    ) {}

    public function execute(string $documentId, UploadedFile $file): DocumentResource
    {
        $document = DocumentModel::query()->findOrFail($documentId);
        $ext = $file->getClientOriginalExtension() ?: 'png';
        $key = sprintf('documents/%s/uploads/%s.%s', $document->id, uniqid(), $ext);

        $this->storage->putFile($key, $file->getRealPath(), $file->getMimeType() ?: 'image/png');

        return $this->resources->create($document, [
            'type' => ResourceType::UserUpload,
            'storage_key' => $key,
            'url' => $this->storage->temporaryUrl($key),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
