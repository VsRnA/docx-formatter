<?php

namespace App\Infrastructure\Document;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Port\HtmlRendererPort;

final class DomainHtmlBuilderAdapter implements HtmlBuilderPort
{
    public function __construct(
        private readonly HtmlRendererPort $renderer,
    ) {}

    public function buildFromDocument(Document $document): string
    {
        $parts = ['<article class="document-root">'];

        foreach ($document->blocks() as $block) {
            $inner = $this->renderer->renderBlock($block) ?? $block->html ?? '<p></p>';
            $parts[] = BlockHtmlWrapper::wrap($block->id, $block->type->value, $inner, $block->meta);
        }

        $parts[] = '</article>';

        return implode("\n", $parts);
    }
}
