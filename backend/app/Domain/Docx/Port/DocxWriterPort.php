<?php

namespace App\Domain\Docx\Port;

use App\Domain\Document\Entity\Document;

interface DocxWriterPort
{
    public function writeTranslated(Document $document, string $sourcePath, string $outputPath): void;
}
