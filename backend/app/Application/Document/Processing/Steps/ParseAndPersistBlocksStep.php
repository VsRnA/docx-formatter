<?php

namespace App\Application\Document\Processing\Steps;

use App\Application\Document\Processing\DocumentProcessingState;
use App\Application\Docx\Service\BlockPersistenceService;
use App\Domain\Document\Entity\Document;
use App\Domain\Document\Port\DocumentPipelineStepPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\ProcessingStage;
use App\Domain\Docx\Entity\ParsedDocument;
use App\Domain\Docx\Port\DocxParserPort;
use App\Domain\Docx\Service\CoveragePolicy;
use App\Infrastructure\Document\CoverageSourceTextExtractor;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Models\Document as DocumentModel;
use RuntimeException;

final class ParseAndPersistBlocksStep implements DocumentPipelineStepPort
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly DocxParserPort $parser,
        private readonly BlockPersistenceService $persister,
        private readonly CoveragePolicy $coverage,
        private readonly CoverageSourceTextExtractor $sourceText,
    ) {}

    public function run(Document $document, object $state): Document
    {
        assert($state instanceof DocumentProcessingState);

        if (! $state->localDocxPath) {
            throw new RuntimeException('Local DOCX path missing before parse step.');
        }

        $document->markProcessing(ProcessingStage::parse());
        $this->documents->save($document);

        $parsed = $this->parser->parse($state->localDocxPath);

        $eloquent = DocumentModel::query()->findOrFail($document->id()->value);
        $this->persister->persist($eloquent, $parsed, $document->shouldTranslate());

        $document = $this->documents->find($document->id());
        $document->markProcessing(ProcessingStage::validate());

        $sourceFragments = $this->sourceText->extractFragments($state->localDocxPath);
        $sourcePlain = OoxmlXml::normalizePlainText(implode(' ', $sourceFragments));
        $blocksPlain = $this->blocksPlainText($parsed);
        $missingFragments = $this->sourceText->findMissingFragments($sourceFragments, $blocksPlain);
        $coverage = $this->coverage->evaluate(
            $sourcePlain,
            $blocksPlain,
            PARSE_COVERAGE_THRESHOLD,
            $missingFragments,
        );

        $document->recordParseResult(
            $coverage,
            $parsed->meta['warnings'] ?? [],
            $parsed->meta ?? [],
        );
        $this->documents->save($document);

        return $this->documents->find($document->id());
    }

    private function blocksPlainText(ParsedDocument $parsed): string
    {
        $parts = [];
        foreach ($parsed->blocks as $block) {
            if ($block->textOriginal) {
                $parts[] = $block->textOriginal;
            }
        }

        return OoxmlXml::normalizePlainText(implode(' ', $parts));
    }
}
