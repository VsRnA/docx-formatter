<?php

namespace App\Application\Block\Command\DeleteBlock;

use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;

final class DeleteBlockHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly HtmlBuilderPort $htmlBuilder,
    ) {}

    public function execute(string $documentId, string $blockId): void
    {
        $document = $this->documents->find(new DocumentId($documentId));
        $document->removeBlock($blockId);
        $document->setHtmlDraft($this->htmlBuilder->buildFromDocument($document));
        $this->documents->save($document);
    }
}
