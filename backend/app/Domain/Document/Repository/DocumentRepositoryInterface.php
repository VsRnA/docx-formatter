<?php

namespace App\Domain\Document\Repository;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Exception\DocumentNotFound;
use App\Domain\Document\ValueObject\DocumentId;

interface DocumentRepositoryInterface
{
    /** @throws DocumentNotFound */
    public function find(DocumentId $id): Document;

    public function insert(Document $document): void;

    public function save(Document $document): void;

    public function delete(DocumentId $id): void;
}
