<?php

namespace App\Infrastructure\Docx\Ooxml\Ir\Renderers;

use App\Domain\Document\Entity\DocumentBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Support\Constants\HtmlCssClasses;

final class TextBlockIrRenderer
{
    /**
     * @param  array<string, mixed>  $ir
     */
    public function render(DocumentBlock $block, array $ir): string
    {
        $inner = $this->renderChildren($block, $ir['children'] ?? []);
        [$tag, $class] = $this->wrapperFor($block);

        $attrs = [];
        if ($class !== '') {
            $attrs[] = 'class="'.$class.'"';
        }

        $paragraphCss = $this->paragraphCss($block->styles ?? []);
        if ($paragraphCss !== []) {
            $attrs[] = 'style="'.implode('; ', $paragraphCss).'"';
        }

        $attrString = $attrs !== [] ? ' '.implode(' ', $attrs) : '';

        return '<'.$tag.$attrString.'>'.$inner.'</'.$tag.'>';
    }

    /**
     * @param  list<array<string, mixed>>  $children
     */
    private function renderChildren(DocumentBlock $block, array $children): string
    {
        $translations = $block->meta['ooxml_segment_translations'] ?? [];
        $parts = [];

        foreach ($children as $child) {
            if (($child['kind'] ?? '') !== 'text') {
                continue;
            }

            $segmentId = $child['segmentId'] ?? null;
            $text = (string) ($child['text'] ?? '');
            if ($segmentId !== null && isset($translations[(int) $segmentId])) {
                $text = (string) $translations[(int) $segmentId];
            } elseif ($block->textTranslated && count($children) === 1) {
                $text = $block->textTranslated;
            }

            if ($text === '') {
                continue;
            }

            if ($segmentId !== null) {
                $parts[] = '<span data-ooxml-seg="'.(int) $segmentId.'">'.e($text).'</span>';
            } else {
                $parts[] = e($text);
            }
        }

        return implode('', $parts);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function wrapperFor(DocumentBlock $block): array
    {
        return match ($block->type) {
            BlockType::Heading => ['h2', ''],
            BlockType::List => ['li', HtmlCssClasses::DOC_LIST],
            BlockType::Caption => ['p', 'doc-caption'],
            BlockType::LinkBlock => ['p', 'doc-link-block'],
            BlockType::ImageText => ['p', 'doc-image-text'],
            default => ['p', ''],
        };
    }

    /**
     * @param  array<string, mixed>  $styles
     * @return list<string>
     */
    private function paragraphCss(array $styles): array
    {
        $paragraph = $styles['paragraph'] ?? [];
        if (! is_array($paragraph)) {
            return [];
        }

        $css = [];
        if (! empty($paragraph['align'])) {
            $css[] = 'text-align:'.$paragraph['align'];
        }

        return $css;
    }
}
