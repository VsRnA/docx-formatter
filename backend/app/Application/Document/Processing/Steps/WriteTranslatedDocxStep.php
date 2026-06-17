<?php

namespace App\Application\Document\Processing\Steps;

use App\Application\Document\Processing\DocumentProcessingState;
use App\Infrastructure\Docx\Ooxml\OoxmlDocxWriter;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\Port\DocumentPipelineStepPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\ProcessingStage;
use App\Domain\Shared\Port\FileStoragePort;
use App\Models\Document as DocumentModel;
use App\Support\TempFileManager;
use RuntimeException;

final class WriteTranslatedDocxStep implements DocumentPipelineStepPort
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly FileStoragePort $storage,
        private readonly OoxmlDocxWriter $docxWriter,
        private readonly TempFileManager $tempFiles,
    ) {}

    public function run(Document $document, object $state): Document
    {
        assert($state instanceof DocumentProcessingState);

        $eloquent = DocumentModel::query()->with('blocks')->findOrFail($document->id()->value);

        if (! $this->docxWriter->documentNeedsPatch($eloquent)) {
            return $document;
        }

        if (! $state->localDocxPath) {
            throw new RuntimeException('Local DOCX path missing before write translated step.');
        }

        $document->markProcessing(ProcessingStage::writeDocx());
        $this->documents->save($document);

        $outputPath = $this->tempFiles->createPath('docx');

        try {
            $stats = $this->docxWriter->writeFromDocument($eloquent, $state->localDocxPath, $outputPath);
            $outputKey = sprintf('documents/%s/translated.docx', $document->id()->value);
            $this->storage->put(
                $outputKey,
                file_get_contents($outputPath) ?: '',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            );

            $document = $this->documents->find($document->id());
            $document->mergeMeta([
                'translated_file_key' => $outputKey,
                'docx_write_stats' => $stats,
            ]);
            $this->documents->save($document);
        } finally {
            $this->tempFiles->cleanup($outputPath);
        }

        return $this->documents->find($document->id());
    }
}
