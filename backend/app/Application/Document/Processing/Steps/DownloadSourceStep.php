<?php

namespace App\Application\Document\Processing\Steps;

use App\Application\Document\Processing\DocumentProcessingState;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\Port\DocumentPipelineStepPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\ProcessingStage;
use App\Domain\Shared\Port\FileStoragePort;
use App\Support\TempFileManager;
use RuntimeException;

final class DownloadSourceStep implements DocumentPipelineStepPort
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly FileStoragePort $storage,
        private readonly TempFileManager $tempFiles,
    ) {}

    public function run(Document $document, object $state): Document
    {
        assert($state instanceof DocumentProcessingState);

        $document->markProcessing(ProcessingStage::download());
        $this->documents->save($document);

        $sourceKey = $document->sourceFileKey();
        if ($sourceKey === null || $sourceKey === '') {
            throw new RuntimeException('Исходный файл не найден. Загрузите документ заново.');
        }

        $state->localDocxPath = $this->tempFiles->createPath('docx');
        $contents = $this->storage->get($sourceKey);
        if ($contents === '') {
            throw new RuntimeException('Исходный файл пустой. Загрузите документ заново.');
        }
        file_put_contents($state->localDocxPath, $contents);

        return $this->documents->find($document->id());
    }
}
