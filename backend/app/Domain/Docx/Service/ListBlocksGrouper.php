<?php

namespace App\Domain\Docx\Service;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;

final class ListBlocksGrouper
{
    /**
     * @param  list<ParsedBlock>  $blocks
     * @return list<ParsedBlock>
     */
    public function group(array $blocks): array
    {
        $result = [];
        $buffer = [];
        $listSort = null;

        $flush = function () use (&$result, &$buffer, &$listSort): void {
            if ($buffer === []) {
                return;
            }

            $items = array_map(
                fn (ParsedBlock $b) => $this->normalizeListItemHtml($b->html),
                $buffer,
            );

            $listTag = $this->resolveListTag($buffer);
            $listClass = $this->resolveListClass($buffer, $listTag);
            $first = $buffer[0];

            $result[] = new ParsedBlock(
                type: BlockType::List,
                sort: $listSort ?? $first->sort,
                html: '<'.$listTag.' class="'.$listClass.'">'.implode('', $items).'</'.$listTag.'>',
                textOriginal: trim(implode("\n", array_filter(array_map(
                    fn (ParsedBlock $b) => $b->textOriginal,
                    $buffer,
                )))),
                meta: [
                    'grouped' => true,
                    'count' => count($buffer),
                    'list_type' => $listTag,
                    'list_marker' => $this->resolveMarkerKind($buffer),
                ],
            );
            $buffer = [];
            $listSort = null;
        };

        foreach ($blocks as $block) {
            if ($block->type === BlockType::List) {
                if ($listSort === null) {
                    $listSort = $block->sort;
                }
                $buffer[] = $block;
            } else {
                $flush();
                $result[] = $block;
            }
        }

        $flush();

        usort($result, fn ($a, $b) => $a->sort <=> $b->sort);

        return $result;
    }

    private function normalizeListItemHtml(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '<li></li>';
        }
        if (preg_match('#^<li[\s>].*</li>$#si', $html)) {
            return $html;
        }

        return '<li>'.$html.'</li>';
    }

    /**
     * @param  list<ParsedBlock>  $items
     */
    private function resolveListTag(array $items): string
    {
        foreach ($items as $item) {
            $marker = (string) ($item->meta['list_marker'] ?? '');
            if ($marker === 'decimal') {
                return 'ol';
            }
            $style = (string) ($item->meta['list_style'] ?? '');
            if (preg_match('/number|decimal|ordered|roman|alpha/i', $style)) {
                return 'ol';
            }
        }

        return 'ul';
    }

    /**
     * @param  list<ParsedBlock>  $items
     */
    private function resolveMarkerKind(array $items): string
    {
        foreach ($items as $item) {
            $marker = (string) ($item->meta['list_marker'] ?? '');
            if ($marker !== '') {
                return $marker;
            }
        }

        return 'disc';
    }

    /**
     * @param  list<ParsedBlock>  $items
     */
    private function resolveListClass(array $items, string $listTag): string
    {
        $marker = $this->resolveMarkerKind($items);

        return match (true) {
            $listTag === 'ol' => 'doc-list doc-list--decimal',
            $marker === 'dash' => 'doc-list doc-list--dash',
            default => 'doc-list doc-list--disc',
        };
    }
}
