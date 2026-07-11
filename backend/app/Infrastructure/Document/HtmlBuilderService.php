<?php

namespace App\Infrastructure\Document;

use App\Domain\Document\Port\HtmlRendererPort;
use App\Models\Document;

class HtmlBuilderService
{
    public function __construct(
        private readonly HtmlRendererPort $renderer,
    ) {}

    public function buildFromDocument(Document $document): string
    {
        $blocks = $document->blocks()->orderBy('sort')->get();
        $parts = ['<article class="document-root">'];

        foreach ($blocks as $block) {
            $inner = $this->renderer->renderBlock($block) ?? $block->html ?? '<p></p>';
            $parts[] = BlockHtmlWrapper::wrap($block->id, $block->type->value, $inner, $block->meta_json);
        }

        $parts[] = '</article>';

        return implode("\n", $parts);
    }
}
