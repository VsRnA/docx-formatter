<?php

namespace App\Infrastructure\Docx\Ooxml\Ir;

use App\Domain\Document\Entity\DocumentBlock;
use App\Domain\Document\Port\HtmlRendererPort;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Docx\Ooxml\Ir\Renderers\FallbackIrRenderer;
use App\Infrastructure\Docx\Ooxml\Ir\Renderers\TextBlockIrRenderer;

final class HtmlRenderer implements HtmlRendererPort
{
    private const LAYOUT_MARKERS = [
        'doc-symbol-row',
        'doc-textbox',
        '<figure',
        'doc-image--page',
    ];

    public function __construct(
        private readonly TextBlockIrRenderer $textBlocks,
        private readonly FallbackIrRenderer $fallback,
    ) {}

    public function renderBlock(DocumentBlock $block): ?string
    {
        if ($this->shouldUseStoredHtml($block)) {
            return null;
        }

        $ir = $block->contentJson;
        if (! is_array($ir) || ! isset($ir['kind'])) {
            return null;
        }

        return match ($ir['kind']) {
            'ooxml_fallback' => $this->fallback->render($block, $ir),
            'paragraph', 'heading', 'list', 'caption', 'link_block', 'image_text' => $this->textBlocks->render($block, $ir),
            default => null,
        };
    }

    private function shouldUseStoredHtml(DocumentBlock $block): bool
    {
        $meta = $block->meta ?? [];
        if ($meta['content_edited'] ?? false) {
            return true;
        }

        $ir = $block->contentJson;
        if (is_array($ir) && ($ir['kind'] ?? '') === 'ooxml_fallback' && ! ($meta['ai_normalized'] ?? false)) {
            return false;
        }

        if ($meta['ai_normalized'] ?? false) {
            return false;
        }

        if ($meta['parse'] ?? false) {
            return true;
        }

        if (in_array($block->type, [BlockType::Table, BlockType::Image, BlockType::Formula], true)) {
            return true;
        }

        $html = $block->html ?? '';
        foreach (self::LAYOUT_MARKERS as $marker) {
            if (str_contains($html, $marker)) {
                return true;
            }
        }

        return false;
    }
}
