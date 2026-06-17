<?php

namespace App\Application\Image\Command\ReplaceImage;

use App\Domain\Document\Repository\ResourceRepositoryInterface;
use App\Domain\Shared\Port\FileStoragePort;
use App\Models\DocumentResource;
use Illuminate\Http\UploadedFile;

final class ReplaceImageHandler
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resources,
        private readonly FileStoragePort $storage,
    ) {}

    public function execute(DocumentResource $resource, UploadedFile $file): DocumentResource
    {
        $this->storage->putFile($resource->storage_key, $file->getRealPath(), $file->getMimeType() ?: 'image/png');

        return $this->resources->update($resource, [
            'url' => $this->storage->temporaryUrl($resource->storage_key),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
