<?php

namespace App\Application\Docx\Service;

use App\Domain\Docx\Entity\ParsedDocument;
use App\Infrastructure\Document\Persist\ParsedBlockPersister;
use App\Models\Document as DocumentModel;

final class BlockPersistenceService
{
    public function __construct(
        private readonly ParsedBlockPersister $persister,
    ) {}

    public function persist(DocumentModel $document, ParsedDocument $parsed, bool $translate): void
    {
        $this->persister->persistAll($document, $parsed, $translate);
    }
}
