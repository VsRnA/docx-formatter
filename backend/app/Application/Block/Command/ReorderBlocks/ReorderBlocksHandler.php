<?php

namespace App\Application\Block\Command\ReorderBlocks;

use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Models\Document as DocumentModel;

final class ReorderBlocksHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly HtmlBuilderPort $htmlBuilder,
    ) {}

    public function execute(string $documentId, array $orderedIds): DocumentModel
    {
        $document = $this->documents->find(new DocumentId($documentId));
        $document->reorderBlocks($orderedIds);
        $document->setHtmlDraft($this->htmlBuilder->buildFromDocument($document));
        $this->documents->save($document);

        return DocumentModel::query()->findOrFail($documentId);
    }
}
