<?php

namespace App\Application\Document\Command\SaveDocumentDraft;

use App\Domain\Document\Entity\DocumentBlock;
use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Port\HtmlSanitizerPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Document\ValueObject\TranslationStatus;
use App\Domain\Docx\ValueObject\BlockType;
use App\Domain\Shared\Port\FileStoragePort;
use App\DTO\Document\SaveDocumentDraftDto;
use App\Infrastructure\Document\EditorHtmlNormalizer;
use App\Infrastructure\Document\Revision\DocumentRevisionService;
use App\Models\Document as DocumentModel;

final class SaveDocumentDraftHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly HtmlSanitizerPort $sanitizer,
        private readonly HtmlBuilderPort $htmlBuilder,
        private readonly EditorHtmlNormalizer $htmlNormalizer,
        private readonly FileStoragePort $storage,
        private readonly DocumentRevisionService $revisions,
    ) {}

    public function execute(SaveDocumentDraftDto $dto): DocumentModel
    {
        $document = $this->documents->find(new DocumentId($dto->documentId));

        foreach ($dto->blocks as $blockDto) {
            $html = $this->htmlNormalizer->normalize($blockDto->html ?? '');
            $html = $this->sanitizer->sanitize($html);
            $html = $this->htmlNormalizer->normalizeImageStorageUrls($html);

            if (! $document->hasBlock($blockDto->id)) {
                $type = BlockType::tryFrom($blockDto->type) ?? BlockType::Paragraph;
                $assets = $type === BlockType::Image
                    ? $this->resolveImageAssets($html, $blockDto->assets)
                    : $blockDto->assets;

                $document->addBlock(new DocumentBlock(
                    id: $blockDto->id,
                    type: $type,
                    sort: $blockDto->sort,
                    html: $html,
                    textOriginal: null,
                    textTranslated: null,
                    translationStatus: TranslationStatus::Skipped,
                    styles: $blockDto->styles,
                    meta: is_array($blockDto->meta) ? $blockDto->meta : null,
                    assets: $assets,
                ));

                continue;
            }

            $block = $document->findBlock($blockDto->id);
            $meta = is_array($blockDto->meta) ? $blockDto->meta : ($block->meta ?? []);
            if (($blockDto->html ?? '') !== ($block->html ?? '')) {
                $meta['content_edited'] = true;
            }

            $assets = $block->assets;
            if ($block->type === BlockType::Image) {
                $assets = $this->resolveImageAssets($html, $blockDto->assets);
            }

            $document->updateBlock($blockDto->id, $block->withContent(
                html: $html,
                styles: $blockDto->styles,
                meta: $meta,
                assets: $assets,
                sort: $blockDto->sort,
            ));
        }

        $document->setHtmlDraft($this->htmlBuilder->buildFromDocument($document));
        $document->setStatus(DocumentStatus::Draft);
        $this->documents->save($document);

        if ($dto->createAutosaveCheckpoint) {
            $this->revisions->maybeCreateAutosaveCheckpoint($dto->documentId);
        }

        return DocumentModel::query()->findOrFail($dto->documentId);
    }

    /**
     * @param  array<string, mixed>|null  $assets
     * @return array<string, mixed>|null
     */
    private function resolveImageAssets(string $html, ?array $assets): ?array
    {
        $key = EditorHtmlNormalizer::storageKeyFromImageSrc($html);
        if ($key === null) {
            return $assets;
        }

        return array_merge($assets ?? [], [
            'storage_key' => $key,
            'url' => $this->storage->temporaryUrl($key),
        ]);
    }
}
