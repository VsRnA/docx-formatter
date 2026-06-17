<?php

namespace App\Domain\Document\Port;

use App\Domain\Document\Entity\DocumentBlock;

interface HtmlRendererPort
{
    public function renderBlock(DocumentBlock $block): ?string;
}
