<?php

namespace App\Application\Document\Command\PublishDocument;

use App\Domain\Document\Port\HtmlSanitizerPort;
use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Models\Document as DocumentModel;
use App\Infrastructure\Document\EditorHtmlNormalizer;
use App\Infrastructure\Document\PublishedHtmlPreparer;
use Illuminate\Support\Str;

final class PublishDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly HtmlSanitizerPort $sanitizer,
        private readonly HtmlBuilderPort $htmlBuilder,
        private readonly EditorHtmlNormalizer $htmlNormalizer,
        private readonly PublishedHtmlPreparer $htmlPreparer,
    ) {}

    public function execute(string $documentId): DocumentModel
    {
        $document = $this->documents->find(new DocumentId($documentId));
        $slug = $document->slug() ?? $this->generateSlug($document->title(), $documentId);

        $html = $document->htmlDraft();
        if (! $html) {
            $html = $this->htmlBuilder->buildFromDocument($document);
        }

        $html = $this->htmlNormalizer->normalizeImageStorageUrls($html);
        $html = $this->sanitizer->sanitize($html);
        $html = $this->htmlNormalizer->normalizeImageStorageUrls($html);
        $html = $this->htmlPreparer->prepareFragment($html);

        $document->setSlug($slug);
        $document->setHtmlPublished($html);
        $document->setStatus(DocumentStatus::Published);
        $this->documents->save($document);

        return DocumentModel::query()->findOrFail($documentId);
    }

    private function generateSlug(string $title, string $id): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'document';
        }

        return $base.'-'.substr(str_replace('-', '', $id), 0, 8);
    }
}
