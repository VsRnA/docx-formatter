<?php

namespace App\Application\Block\Command\DuplicateBlock;

use App\Application\Block\Command\CreateBlock\CreateBlockHandler;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Models\DocumentBlock as DocumentBlockModel;

final class DuplicateBlockHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly CreateBlockHandler $createBlock,
    ) {}

    public function execute(string $documentId, string $blockId): DocumentBlockModel
    {
        $document = $this->documents->find(new DocumentId($documentId));
        $source = $document->findBlock($blockId);

        return $this->createBlock->execute($documentId, [
            'type' => $source->type->value,
            'sort' => $source->sort + 1,
            'html' => $source->html,
            'text_original' => $source->textOriginal,
        ]);
    }
}
