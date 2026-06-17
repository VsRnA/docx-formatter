<?php

namespace App\Application\Document\Command\ProcessDocument;

use App\Application\Document\Processing\DocumentProcessingPipeline;

final class ProcessDocumentHandler
{
    public function __construct(
        private readonly DocumentProcessingPipeline $pipeline,
    ) {}

    public function execute(string $documentId): void
    {
        $this->pipeline->run($documentId);
    }
}
