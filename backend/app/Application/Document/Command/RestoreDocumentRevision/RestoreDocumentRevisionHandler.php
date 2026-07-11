<?php

namespace App\Application\Document\Command\RestoreDocumentRevision;

use App\Infrastructure\Document\Revision\DocumentRevisionService;
use App\Models\Document;

final class RestoreDocumentRevisionHandler
{
    public function __construct(
        private readonly DocumentRevisionService $revisions,
    ) {}

    public function execute(string $documentId, string $revisionId): Document
    {
        return $this->revisions->restore($documentId, $revisionId);
    }
}
