<?php

namespace App\Application\Document\Query\ListDocumentRevisions;

use App\Infrastructure\Document\Revision\DocumentRevisionService;
use App\Models\DocumentRevision;

final class ListDocumentRevisionsHandler
{
    public function __construct(
        private readonly DocumentRevisionService $revisions,
    ) {}

    /**
     * @return list<DocumentRevision>
     */
    public function execute(string $documentId, int $limit = 50): array
    {
        return $this->revisions->list($documentId, $limit);
    }
}
