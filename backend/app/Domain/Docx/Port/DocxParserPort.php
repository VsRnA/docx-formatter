<?php

namespace App\Domain\Docx\Port;

use App\Domain\Docx\Entity\ParsedDocument;

interface DocxParserPort
{
    public function parse(string $localPath): ParsedDocument;
}
