<?php

namespace App\Application\Document\Query\GetPublicDocument;

use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Models\Document as DocumentModel;
use App\Infrastructure\Document\PublishedHtmlPreparer;
use App\Support\DocumentTitle;

final class GetPublicDocumentHandler
{
    public function __construct(
        private readonly HtmlBuilderPort $htmlBuilder,
        private readonly PublishedHtmlPreparer $htmlPreparer,
        private readonly \App\Domain\Document\Repository\DocumentRepositoryInterface $documents,
    ) {}

    /**
     * @return array{title: string, html: string}|null
     */
    public function execute(string $slug): ?array
    {
        $model = DocumentModel::query()->where('slug', $slug)->first();
        if ($model === null) {
            return null;
        }

        $document = $this->documents->find(new \App\Domain\Document\ValueObject\DocumentId($model->id));

        if ($document->status() !== DocumentStatus::Published) {
            return null;
        }

        $fragment = $document->htmlPublished() ?: $this->htmlBuilder->buildFromDocument($document);
        if ($fragment === '') {
            return null;
        }

        return [
            'title' => DocumentTitle::displayFromDomain($document),
            'html' => $this->htmlPreparer->prepareFragment($fragment),
        ];
    }
}
