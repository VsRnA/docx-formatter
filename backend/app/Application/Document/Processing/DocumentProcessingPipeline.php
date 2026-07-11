<?php

namespace App\Application\Document\Processing;

use App\Domain\Document\Port\DocumentPipelineStepPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Support\TempFileManager;
use Throwable;

final class DocumentProcessingPipeline
{
    /** @param  iterable<DocumentPipelineStepPort>  $steps */
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly TempFileManager $tempFiles,
        private readonly iterable $steps,
    ) {}

    public function run(string $documentId): void
    {
        $document = $this->documents->find(new DocumentId($documentId));
        $state = new DocumentProcessingState($documentId);

        try {
            foreach ($this->steps as $step) {
                $document = $step->run($document, $state);
            }
        } catch (Throwable $e) {
            $document = $this->documents->find(new DocumentId($documentId));
            $document->markFailed($e->getMessage());
            $this->documents->save($document);
            throw $e;
        } finally {
            if ($state->localDocxPath) {
                $this->tempFiles->cleanup($state->localDocxPath);
            }
        }
    }
}
