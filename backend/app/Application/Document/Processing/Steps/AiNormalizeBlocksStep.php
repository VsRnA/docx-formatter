<?php

namespace App\Application\Document\Processing\Steps;

use App\Application\Document\Processing\DocumentProcessingState;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\Port\DocumentPipelineStepPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\ProcessingStage;
use App\Models\Document as DocumentModel;
use App\Infrastructure\Document\Normalize\BlockNormalizationService;

final class AiNormalizeBlocksStep implements DocumentPipelineStepPort
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly BlockNormalizationService $normalizer,
    ) {}

    public function run(Document $document, object $state): Document
    {
        assert($state instanceof DocumentProcessingState);

        $document->markProcessing(ProcessingStage::normalize());
        $this->documents->save($document);

        $eloquent = DocumentModel::query()->findOrFail($document->id()->value);
        $result = $this->normalizer->normalizeDocument($eloquent, MAX_AI_NORMALIZE_BLOCKS_PER_DOCUMENT);

        $document = $this->documents->find($document->id());
        $document->recordNormalization($result['normalized'], $result['skipped']);
        $this->documents->save($document);

        return $this->documents->find($document->id());
    }
}
