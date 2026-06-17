<?php

namespace App\Domain\Document\Port;

use App\Domain\Document\Entity\Document;

interface HtmlBuilderPort
{
    public function buildFromDocument(Document $document): string;
}
