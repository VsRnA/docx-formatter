<?php

namespace App\Application\Document\Query\ExportDocumentHtml;

use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Infrastructure\Document\PublishedHtmlPreparer;
use App\Support\DocumentTitle;

final class ExportDocumentHtmlHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly HtmlBuilderPort $htmlBuilder,
        private readonly PublishedHtmlPreparer $htmlPreparer,
    ) {}

    public function execute(string $documentId, bool $usePublished = false): string
    {
        $document = $this->documents->find(new DocumentId($documentId));

        $fragment = $usePublished && $document->htmlPublished()
            ? $document->htmlPublished()
            : $this->htmlBuilder->buildFromDocument($document);

        return $this->htmlPreparer->prepareStandalone(
            DocumentTitle::displayFromDomain($document),
            $fragment,
        );
    }
}
