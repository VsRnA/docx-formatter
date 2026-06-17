<?php

namespace App\Infrastructure\Docx\Ooxml\Ir\Renderers;

use App\Domain\Document\Entity\DocumentBlock;
use App\Support\Constants\HtmlCssClasses;

final class FallbackIrRenderer
{
    /**
     * @param  array<string, mixed>  $ir
     */
    public function render(DocumentBlock $block, array $ir): string
    {
        $localName = (string) ($ir['localName'] ?? 'unknown');
        $text = $block->textTranslated ?? $block->textOriginal ?? '';
        $display = $text !== ''
            ? e($text)
            : '[unsupported: '.e($localName).']';

        return '<div class="'.HtmlCssClasses::DOC_RAW_OOXML.'" data-ooxml-tag="'
            .e($localName).'">'.$display.'</div>';
    }
}
