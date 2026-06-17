<?php

namespace App\Domain\Document\Repository;

use App\Models\Document;
use App\Models\DocumentResource;

interface ResourceRepositoryInterface
{
    public function create(Document $document, array $attributes): DocumentResource;

    public function update(DocumentResource $resource, array $attributes): DocumentResource;

    public function delete(DocumentResource $resource): void;
}
