<?php

namespace App\Infrastructure\Docx\Adapter;

use App\Domain\Docx\Entity\ParsedDocument;
use App\Domain\Docx\Port\DocxParserPort;
use App\Infrastructure\Docx\Ooxml\OoxmlNativeDocxParser;

final class NativeDocxParserAdapter implements DocxParserPort
{
    public function __construct(
        private readonly OoxmlNativeDocxParser $parser,
    ) {}

    public function parse(string $localPath): ParsedDocument
    {
        return $this->parser->parse($localPath);
    }
}
