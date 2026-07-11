<?php

namespace App\Application\Block\Command\UpdateBlock;

use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Port\HtmlSanitizerPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Infrastructure\Document\EditorHtmlNormalizer;
use App\Models\DocumentBlock as DocumentBlockModel;

final class UpdateBlockHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly HtmlSanitizerPort $sanitizer,
        private readonly HtmlBuilderPort $htmlBuilder,
        private readonly EditorHtmlNormalizer $htmlNormalizer,
    ) {}

    public function execute(string $documentId, string $blockId, array $payload): DocumentBlockModel
    {
        $document = $this->documents->find(new DocumentId($documentId));
        $block = $document->findBlock($blockId);

        $html = isset($payload['html'])
            ? $this->sanitizer->sanitize($this->htmlNormalizer->normalize($payload['html']))
            : $block->html;

        $document->updateBlock($blockId, $block->withContent(
            html: $html,
            styles: $payload['styles_json'] ?? $block->styles,
            meta: $payload['meta_json'] ?? $block->meta,
            sort: $payload['sort'] ?? $block->sort,
        ));

        $document->setHtmlDraft($this->htmlBuilder->buildFromDocument($document));
        $this->documents->save($document);

        return DocumentBlockModel::query()->findOrFail($blockId);
    }
}
