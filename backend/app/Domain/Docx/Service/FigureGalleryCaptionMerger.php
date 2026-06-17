<?php

namespace App\Domain\Docx\Service;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;

/**
 * Attaches the caption row that follows a figure gallery (Fig.2A / Fig.2B / …)
 * as centered figcaptions under each image cell.
 */
final class FigureGalleryCaptionMerger
{
    private const CAPTION_LABEL_PATTERN = '/Fig\.\s*\d+\s*[A-Z]?/i';
    /**
     * @param  list<ParsedBlock>  $blocks
     * @return list<ParsedBlock>
     */
    public function merge(array $blocks): array
    {
        $result = [];
        $count = count($blocks);

        for ($index = 0; $index < $count; $index++) {
            $block = $blocks[$index];
            $next = $blocks[$index + 1] ?? null;

            if ($next !== null && $this->isFigureGalleryBlock($block) && $this->isCaptionBlock($next)) {
                $result[] = $this->attachCaptions($block, $next);
                $index++;

                continue;
            }

            $result[] = $block;
        }

        return $result;
    }

    private function isFigureGalleryBlock(ParsedBlock $block): bool
    {
        if ($block->type !== BlockType::Paragraph) {
            return false;
        }

        return str_contains((string) $block->html, 'doc-figure-gallery')
            || str_contains((string) $block->html, 'doc-figure-canvas');
    }

    private function isCaptionBlock(ParsedBlock $block): bool
    {
        if ($block->type !== BlockType::Paragraph) {
            return false;
        }

        $html = (string) $block->html;
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');

        if ($plain === '') {
            return false;
        }

        if (preg_match(self::CAPTION_LABEL_PATTERN, $plain) !== 1) {
            return false;
        }

        return ! str_contains($html, '<figure') && ! str_contains($html, 'doc-figure-gallery');
    }

    private function attachCaptions(ParsedBlock $gallery, ParsedBlock $captionBlock): ParsedBlock
    {
        $captions = $this->extractCaptions((string) $captionBlock->html);
        $captionIndex = 0;

        $html = preg_replace_callback(
            '/<figcaption class="doc-figure-caption"([^>]*)><\/figcaption>/',
            function (array $matches) use (&$captionIndex, $captions): string {
                $caption = $captions[$captionIndex] ?? '';
                $captionIndex++;

                return '<figcaption class="doc-figure-caption"'.$matches[1].'>'.$caption.'</figcaption>';
            },
            (string) $gallery->html,
        );

        $meta = $gallery->meta ?? [];
        if ($captionBlock->meta['ooxml_segments'] ?? null) {
            $meta['ooxml_segments'] = array_values(array_merge(
                $meta['ooxml_segments'] ?? [],
                $captionBlock->meta['ooxml_segments'],
            ));
        }

        return new ParsedBlock(
            type: $gallery->type,
            sort: $gallery->sort,
            html: is_string($html) ? $html : $gallery->html,
            textOriginal: trim(((string) $gallery->textOriginal).' '.strip_tags((string) $captionBlock->html)),
            styles: $gallery->styles,
            meta: $meta,
            assets: $gallery->assets,
            localImagePath: $gallery->localImagePath,
        );
    }

    /**
     * @return list<string>
     */
    private function extractCaptions(string $html): array
    {
        if (preg_match_all('/(<span\b[^>]*>\s*Fig\.\s*\d+\s*[A-Z]?\s*<\/span>)/i', $html, $matches)) {
            return array_map(static function (string $caption): string {
                if (preg_match('/^(<span\b[^>]*>)(.*?)(<\/span>)$/is', $caption, $parts)) {
                    $inner = preg_replace('/\s+/u', ' ', trim(strip_tags($parts[2]))) ?? '';

                    return $parts[1].e($inner).$parts[3];
                }

                return trim($caption);
            }, $matches[1]);
        }

        preg_match_all(self::CAPTION_LABEL_PATTERN, strip_tags($html), $plainMatches);

        return array_map(
            static function (string $caption): string {
                $caption = preg_replace('/\s+/u', ' ', trim(strip_tags($caption))) ?? trim($caption);

                return '<span>'.e($caption).'</span>';
            },
            $plainMatches[0] ?? [],
        );
    }
}
