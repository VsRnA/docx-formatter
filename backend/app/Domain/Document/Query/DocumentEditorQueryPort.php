<?php

namespace App\Domain\Document\Query;

use App\Application\Document\Query\GetDocumentEditor\DocumentEditorReadModel;
use App\Domain\Document\Exception\DocumentNotFound;
use App\Domain\Document\ValueObject\DocumentId;

interface DocumentEditorQueryPort
{
    /** @throws DocumentNotFound */
    public function load(DocumentId $id): DocumentEditorReadModel;
}
