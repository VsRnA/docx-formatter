<?php

namespace App\Application\Image\Command\DeleteImage;

use App\Domain\Document\Repository\ResourceRepositoryInterface;
use App\Domain\Shared\Port\FileStoragePort;
use App\Models\DocumentResource;

final class DeleteImageHandler
{
    public function __construct(
        private readonly ResourceRepositoryInterface $resources,
        private readonly FileStoragePort $storage,
    ) {}

    public function execute(DocumentResource $resource): void
    {
        $this->storage->delete($resource->storage_key);
        $this->resources->delete($resource);
    }
}
