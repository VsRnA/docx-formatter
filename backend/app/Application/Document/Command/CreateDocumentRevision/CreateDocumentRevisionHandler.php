<?php

namespace App\Application\Document\Command\CreateDocumentRevision;

use App\Infrastructure\Document\Revision\DocumentRevisionService;
use App\Models\DocumentRevision;

final class CreateDocumentRevisionHandler
{
    public function __construct(
        private readonly DocumentRevisionService $revisions,
    ) {}

    public function execute(string $documentId, ?string $label = null): DocumentRevision
    {
        return $this->revisions->createManualSnapshot($documentId, $label);
    }
}
