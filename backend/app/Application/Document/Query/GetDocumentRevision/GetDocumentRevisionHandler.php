<?php

namespace App\Application\Document\Query\GetDocumentRevision;

use App\Infrastructure\Document\Revision\DocumentRevisionService;
use App\Models\DocumentRevision;

final class GetDocumentRevisionHandler
{
    public function __construct(
        private readonly DocumentRevisionService $revisions,
    ) {}

    public function execute(string $documentId, string $revisionId): DocumentRevision
    {
        return $this->revisions->find($documentId, $revisionId);
    }
}
