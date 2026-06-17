<?php

namespace App\Application\Block\Command\CreateBlock;

use App\Domain\Document\Entity\DocumentBlock;
use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\TranslationStatus;
use App\Domain\Docx\ValueObject\BlockType;
use App\Models\DocumentBlock as DocumentBlockModel;
use Illuminate\Support\Str;

final class CreateBlockHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly HtmlBuilderPort $htmlBuilder,
    ) {}

    public function execute(string $documentId, array $payload): DocumentBlockModel
    {
        $document = $this->documents->find(new DocumentId($documentId));
        $sort = (int) ($payload['sort'] ?? $document->maxBlockSort() + 1);
        $type = BlockType::from($payload['type'] ?? BlockType::Paragraph->value);
        $blockId = (string) Str::uuid();

        $document->addBlock(new DocumentBlock(
            id: $blockId,
            type: $type,
            sort: $sort,
            html: $payload['html'] ?? '<p></p>',
            textOriginal: $payload['text_original'] ?? null,
            textTranslated: null,
            translationStatus: TranslationStatus::Skipped,
        ));

        $document->setHtmlDraft($this->htmlBuilder->buildFromDocument($document));
        $this->documents->save($document);

        return DocumentBlockModel::query()->findOrFail($blockId);
    }
}
