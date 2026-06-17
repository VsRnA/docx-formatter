<?php

namespace App\Domain\Docx\Service;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Docx\Ooxml\Parsing\Layout\AnchoredCanvasLayoutNormalizer;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;

/**
 * Merges consecutive anchored callout paragraphs (short numeric labels in
 * textboxes, optionally with a product photo) into one positioned canvas.
 */
final class AnchoredCalloutBlockMerger
{
    public function __construct(
        private readonly AnchoredCanvasLayoutNormalizer $canvasNormalizer = new AnchoredCanvasLayoutNormalizer,
    ) {}
    /**
     * @param  list<ParsedBlock>  $blocks
     * @return list<ParsedBlock>
     */
    public function merge(array $blocks): array
    {
        $result = [];
        $buffer = [];

        foreach ($blocks as $block) {
            if ($this->isCalloutBlock($block)) {
                $buffer[] = $block;

                continue;
            }

            if ($buffer !== []) {
                $result[] = $this->mergeBuffer($buffer);
                $buffer = [];
            }

            $result[] = $block;
        }

        if ($buffer !== []) {
            $result[] = $this->mergeBuffer($buffer);
        }

        return array_map(
            fn (ParsedBlock $block): ParsedBlock => $this->normalizeExistingCanvas($block),
            $result,
        );
    }

    private function normalizeExistingCanvas(ParsedBlock $block): ParsedBlock
    {
        $html = (string) ($block->html ?? '');
        if (! str_contains($html, 'doc-anchored-canvas') || str_contains($html, 'doc-figure-canvas')) {
            return $block;
        }

        $normalized = $this->canvasNormalizer->normalize($html);

        if ($normalized === $html) {
            return $block;
        }

        return new ParsedBlock(
            type: $block->type,
            sort: $block->sort,
            html: $normalized,
            textOriginal: $block->textOriginal,
            styles: $block->styles,
            meta: $block->meta,
            assets: $block->assets,
            localImagePath: $block->localImagePath,
        );
    }

    private function isCalloutBlock(ParsedBlock $block): bool
    {
        if ($block->type !== BlockType::Paragraph) {
            return false;
        }

        $html = (string) ($block->html ?? '');
        if (! str_contains($html, 'doc-textbox') && ! str_contains($html, 'doc-anchor-shape')) {
            return false;
        }

        $plain = trim((string) ($block->textOriginal ?? strip_tags($html)));

        if ($plain === '') {
            return str_contains($html, 'doc-textbox--anchored')
                || str_contains($html, 'doc-image--anchored')
                || str_contains($html, 'doc-anchor-shape');
        }

        if (str_contains($html, 'doc-anchored-canvas')
            || (str_contains($html, 'doc-anchor-shape') && str_contains($html, '<figure'))) {
            return true;
        }

        if (preg_match('/^\d{1,3}$/u', $plain) === 1) {
            return true;
        }

        return str_contains($html, '<figure')
            && mb_strlen($plain) <= 4
            && preg_match('/^\d+$/u', $plain) === 1;
    }

    /**
     * @param  list<ParsedBlock>  $blocks
     */
    private function mergeBuffer(array $blocks): ParsedBlock
    {
        if (count($blocks) === 1) {
            return $this->ensureAnchoredCanvas($blocks[0]);
        }

        $base = $blocks[0];
        foreach ($blocks as $block) {
            if (str_contains((string) ($block->html ?? ''), '<figure')) {
                $base = $block;

                break;
            }
        }

        $innerParts = [];
        $plainParts = [];
        $segments = [];
        $pendingImages = [];
        $maxBottom = 0;

        foreach ($blocks as $block) {
            $innerParts[] = $this->extractInnerHtml((string) ($block->html ?? ''));
            $plain = trim((string) ($block->textOriginal ?? ''));
            if ($plain !== '') {
                $plainParts[] = $plain;
            }

            $metaSegments = $block->meta['ooxml_segments'] ?? null;
            if (is_array($metaSegments)) {
                foreach ($metaSegments as $segment) {
                    $segments[] = $segment;
                }
            }

            $metaPending = $block->meta['pending_images'] ?? null;
            if (is_array($metaPending)) {
                foreach ($metaPending as $pending) {
                    $pendingImages[] = $pending;
                }
            }

            $maxBottom = max($maxBottom, $this->estimateBottomPx((string) ($block->html ?? '')));
        }

        $combinedInner = implode('', $innerParts);
        $canvasRules = ['position:relative'];
        if ($maxBottom > 0) {
            $canvasRules[] = 'min-height:'.($maxBottom + 8).'px';
        }

        $html = '<div'.OoxmlCss::styleAttribute(['text-align: left']).' class="doc-paragraph--symbols">'
            .'<div class="doc-anchored-canvas"'.OoxmlCss::styleAttribute($canvasRules).'>'
            .$combinedInner
            .'</div></div>';

        $html = $this->canvasNormalizer->normalize($html);

        $meta = $base->meta ?? [];
        if ($segments !== []) {
            $meta['ooxml_segments'] = $this->reindexSegments($segments);
        }
        if ($pendingImages !== []) {
            $meta['pending_images'] = $pendingImages;
        }

        return new ParsedBlock(
            type: $base->type,
            sort: $base->sort,
            html: $html,
            textOriginal: $plainParts !== [] ? implode(' ', $plainParts) : $base->textOriginal,
            styles: $base->styles,
            meta: $meta,
            assets: $base->assets,
            localImagePath: $base->localImagePath,
        );
    }

    private function ensureAnchoredCanvas(ParsedBlock $block): ParsedBlock
    {
        $html = (string) ($block->html ?? '');
        if (str_contains($html, 'doc-anchored-canvas')) {
            $normalized = $this->canvasNormalizer->normalize($html);
            if ($normalized === $html) {
                return $block;
            }

            return new ParsedBlock(
                type: $block->type,
                sort: $block->sort,
                html: $normalized,
                textOriginal: $block->textOriginal,
                styles: $block->styles,
                meta: $block->meta,
                assets: $block->assets,
                localImagePath: $block->localImagePath,
            );
        }

        if (! str_contains($html, 'doc-textbox--anchored') && ! str_contains($html, 'doc-image--anchored')) {
            return $block;
        }

        $inner = $this->extractInnerHtml($html);
        $maxBottom = $this->estimateBottomPx($html);
        $canvasRules = ['position:relative'];
        if ($maxBottom > 0) {
            $canvasRules[] = 'min-height:'.($maxBottom + 8).'px';
        }

        $wrapped = '<div'.OoxmlCss::styleAttribute(['text-align: left']).' class="doc-paragraph--symbols">'
            .'<div class="doc-anchored-canvas"'.OoxmlCss::styleAttribute($canvasRules).'>'
            .$inner
            .'</div></div>';

        $wrapped = $this->canvasNormalizer->normalize($wrapped);

        return new ParsedBlock(
            type: $block->type,
            sort: $block->sort,
            html: $wrapped,
            textOriginal: $block->textOriginal,
            styles: $block->styles,
            meta: $block->meta,
            assets: $block->assets,
            localImagePath: $block->localImagePath,
        );
    }

    private function extractInnerHtml(string $html): string
    {
        $html = trim($html);
        if (preg_match('#^<div[^>]*class="[^"]*doc-paragraph--symbols[^"]*"[^>]*>(.*)</div>$#su', $html, $match) === 1) {
            $html = trim($match[1]);
        }

        if (preg_match('#^<div[^>]*class="[^"]*doc-anchored-canvas[^"]*"[^>]*>(.*)</div>$#su', $html, $match) === 1) {
            return trim($match[1]);
        }

        return $html;
    }

    private function estimateBottomPx(string $html): int
    {
        $maxBottom = 0;

        if (preg_match_all('/style="([^"]*)"/', $html, $matches)) {
            foreach ($matches[1] as $style) {
                $top = 0;
                $height = 0;

                if (preg_match('/top\s*:\s*(\d+)px/i', $style, $match)) {
                    $top = (int) $match[1];
                }

                if (preg_match('/height\s*:\s*(\d+)px/i', $style, $match)) {
                    $height = (int) $match[1];
                } elseif (preg_match('/min-height\s*:\s*(\d+)px/i', $style, $match)) {
                    $height = (int) $match[1];
                }

                $maxBottom = max($maxBottom, $top + $height);
            }
        }

        if (preg_match_all('/height="(\d+)"/', $html, $matches)) {
            foreach ($matches[1] as $height) {
                $maxBottom = max($maxBottom, (int) $height);
            }
        }

        if (preg_match_all('/data-anchor-top="(\d+)"/', $html, $matches)) {
            foreach ($matches[1] as $top) {
                $maxBottom = max($maxBottom, (int) $top + 20);
            }
        }

        return $maxBottom;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<array<string, mixed>>
     */
    private function reindexSegments(array $segments): array
    {
        $result = [];

        foreach ($segments as $index => $segment) {
            $segment['id'] = $index;
            $result[] = $segment;
        }

        return $result;
    }
}
