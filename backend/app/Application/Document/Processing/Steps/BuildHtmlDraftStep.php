<?php

namespace App\Application\Document\Processing\Steps;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Port\DocumentPipelineStepPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\ProcessingStage;

final class BuildHtmlDraftStep implements DocumentPipelineStepPort
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly HtmlBuilderPort $htmlBuilder,
    ) {}

    public function run(Document $document, object $state): Document
    {
        $document->markProcessing(ProcessingStage::buildHtml());
        $this->documents->save($document);

        $document = $this->documents->find($document->id());
        $html = $this->htmlBuilder->buildFromDocument($document);
        $document->markReady($html);
        $this->documents->save($document);

        return $this->documents->find($document->id());
    }
}
