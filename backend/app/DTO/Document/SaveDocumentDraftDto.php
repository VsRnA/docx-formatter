<?php

namespace App\DTO\Document;

final readonly class SaveDocumentDraftDto
{
    /**
     * @param  SaveDraftBlockDto[]  $blocks
     */
    public function __construct(
        public string $documentId,
        public array $blocks,
        public bool $createAutosaveCheckpoint = false,
    ) {}
}
