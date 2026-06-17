<?php

namespace App\Application\Document\Processing;

final class DocumentProcessingState
{
    public ?string $localDocxPath = null;

    public function __construct(public readonly string $documentId) {}
}
