<?php

namespace App\Application\Document\Command\ReprocessDocument;

use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Document\ValueObject\ProcessingStage;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document as DocumentModel;

final class ReprocessDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
    ) {}

    public function execute(string $documentId): DocumentModel
    {
        $document = $this->documents->find(new DocumentId($documentId));
        $document->setStatus(DocumentStatus::Processing);
        $document->markProcessing(ProcessingStage::queued());
        $this->documents->save($document);

        ProcessDocumentJob::dispatch($documentId);

        return DocumentModel::query()->findOrFail($documentId);
    }
}
