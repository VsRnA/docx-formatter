<?php

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\Document\Repository\ResourceRepositoryInterface;
use App\Models\Document;
use App\Models\DocumentResource;

final class EloquentResourceRepository implements ResourceRepositoryInterface
{
    public function create(Document $document, array $attributes): DocumentResource
    {
        return $document->resources()->create($attributes);
    }

    public function update(DocumentResource $resource, array $attributes): DocumentResource
    {
        $resource->update($attributes);

        return $resource->fresh();
    }

    public function delete(DocumentResource $resource): void
    {
        $resource->delete();
    }
}
